<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCorrections
{
    public function snapshot(int $id): array
    {
        $order = DB::table('orders')->find($id);
        abort_unless($order, 404);

        return ['order' => (array) $order, 'items' => DB::table('order_items')->where('order_id', $id)->orderBy('id')->get()->map(fn ($i) => (array) $i)->all(), 'paid' => app(Operations::class)->paid($id), 'revision' => DB::table('order_corrections')->where('order_id', $id)->max('id')];
    }

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot));
    }

    public function preview(int $id, array $data, string $reason, string $expected): array
    {
        validator(['reason' => $reason], ['reason' => 'required|string|min:5|max:2000'])->validate();
        $before = $this->snapshot($id);
        if (! hash_equals($this->fingerprint($before), $expected)) {
            throw ValidationException::withMessages(['operation' => 'This order or its payments changed. Reopen Edit Order to review the latest values.']);
        }
        if ($before['order']['status'] === 'Cancelled') {
            throw ValidationException::withMessages(['operation' => 'Cancelled orders retain their recorded amounts.']);
        }
        $prices = [];
        $subtotal = 0;
        foreach ($before['items'] as $item) {
            $prices[$item['id']] = Money::parse($data['prices'][$item['id']] ?? '');
            $subtotal += $prices[$item['id']] * $item['quantity'];
        }
        $discount = Money::parse($data['discount'] ?? '');
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['operation' => 'Discount cannot exceed merchandise subtotal.']);
        }
        $values = ['subtotal' => $subtotal, 'discount' => $discount, 'delivery' => Money::parse($data['delivery'] ?? ''), 'courier' => Money::parse($data['courier'] ?? '')];
        $values['total'] = $subtotal - $discount + $values['delivery'];

        return ['before' => $before, 'values' => $values, 'prices' => $prices, 'reason' => $reason, 'fingerprint' => $expected];
    }

    public function save(int $id, array $data, string $reason, string $key, array $review): void
    {
        app(Operations::class)->transaction(function () use ($id, $data, $reason, $key, $review) {
            if (DB::table('order_corrections')->where('request_key', $key)->exists()) {
                return;
            }
            $preview = $this->preview($id, $data, $reason, $review['fingerprint'] ?? '');
            if ($preview !== $review) {
                throw ValidationException::withMessages(['operation' => 'Values changed after preview. Review again before confirming.']);
            }
            foreach ($preview['prices'] as $item => $price) {
                DB::table('order_items')->where('id', $item)->update(['unit_price' => $price]);
            }
            DB::table('orders')->where('id', $id)->update($preview['values'] + ['updated_at' => now()]);
            // Reallocate merchandise only; saved quantities and cost basis never change.
            $allocations = app(Operations::class)->allocations($id)->where('dispatched', true);
            $remaining = $preview['values']['subtotal'] - $preview['values']['discount'];
            foreach ($allocations as $a) {
                $gross = Money::allocate($a->unit_price, $a->quantity, $a->pack_quantity);
                $share = $a->id === $allocations->last()->id ? $remaining : Money::allocate($gross, $preview['values']['subtotal'] - $preview['values']['discount'], $preview['values']['subtotal']);
                $remaining -= $share;
                DB::table('allocations')->where('id', $a->id)->update(['merchandise' => $share]);
            }
            DB::table('order_corrections')->insert(['order_id' => $id, 'user_id' => auth()->id(), 'request_key' => $key, 'reason' => $reason, 'before' => json_encode($preview['before']), 'after' => json_encode($this->snapshot($id)), 'created_at' => now()]);
            DB::table('order_events')->insert(['order_id' => $id, 'action' => 'Order amounts corrected', 'business_date' => today()->toDateString(), 'details' => $reason.' · '.Money::format($preview['before']['order']['total']).' → '.Money::format($preview['values']['total']).' · administrator #'.auth()->id(), 'created_at' => now()]);
        });
    }
}
