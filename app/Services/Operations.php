<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Operations
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    public function transaction(callable $action): mixed
    {
        return DB::transaction(function () use ($action) {
            DB::table('operation_locks')->where('id', 1)->lockForUpdate()->first();

            return $action();
        }, 3);
    }

    public function saveOrder(array $data, array $lines, string $key, ?int $id = null): int
    {
        return $this->transaction(function () use ($data, $lines, $key, $id) {
            if (! $id && ($existing = DB::table('orders')->where('request_key', $key)->first())) {
                return $existing->id;
            }
            $old = $id ? DB::table('orders')->find($id) : null;
            if ($id && (! $old || $old->status !== 'Pending')) {
                $this->fail('Only pending orders can be edited.');
            }
            if ($old) {
                foreach ($this->allocations($id) as $a) {
                    DB::table('batches')->where('id', $a->batch_id)->decrement('reserved', $a->quantity);
                }
                DB::table('allocations')->whereIn('order_item_id', DB::table('order_items')->where('order_id', $id)->pluck('id'))->delete();
                DB::table('order_items')->where('order_id', $id)->delete();
            }
            $items = [];
            $subtotal = 0;
            foreach ($lines as $line) {
                $product = DB::table('products')->find($line['product_id']);
                if (! $product) {
                    $this->fail('Select a valid product.');
                }
                $price = Money::parse($line['price']);
                $quantity = (int) $line['quantity'];
                if ($quantity < 1 || $quantity > 10000) {
                    $this->fail('Quantity must be between 1 and 10,000.');
                }
                $subtotal += $price * $quantity;
                $items[] = [$product, $price, $quantity];
            }
            if (! $items) {
                $this->fail('Add at least one item.');
            }
            $discount = Money::parse($data['discount']);
            $delivery = Money::parse($data['delivery']);
            if ($discount > $subtotal) {
                $this->fail('Discount cannot exceed merchandise subtotal.');
            }
            $total = $subtotal - $discount + $delivery;
            if ($old && $total < $this->paid($id)) {
                $this->fail('Total cannot be reduced below net receipts.');
            }
            $values = array_intersect_key($data, array_flip(['customer', 'phone', 'address', 'ordered_on', 'tracking', 'notes']));
            $values += ['subtotal' => $subtotal, 'discount' => $discount, 'delivery' => $delivery, 'courier' => Money::parse($data['courier']), 'total' => $total, 'updated_at' => now()];
            if ($old) {
                DB::table('orders')->where('id', $id)->update($values);
            } else {
                $id = DB::table('orders')->insertGetId($values + ['request_key' => $key, 'number' => 'HB-'.now()->format('ymd').'-'.Str::upper(Str::random(8)), 'created_at' => now()]);
            }
            foreach ($items as [$product, $price, $quantity]) {
                $itemId = DB::table('order_items')->insertGetId(['order_id' => $id, 'product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'pack_quantity' => $product->pack_quantity, 'unit_price' => $price]);
                $remaining = $quantity * $product->pack_quantity;
                $batches = DB::table('batches')->where('stock_key', $product->stock_key)->orderBy('received_on')->orderBy('id')->lockForUpdate()->get();
                foreach ($batches as $batch) {
                    $take = min($remaining, $batch->physical - $batch->reserved);
                    if ($take <= 0) {
                        continue;
                    }
                    DB::table('allocations')->insert(['order_item_id' => $itemId, 'batch_id' => $batch->id, 'quantity' => $take]);
                    DB::table('batches')->where('id', $batch->id)->increment('reserved', $take);
                    $remaining -= $take;
                    if ($remaining === 0) {
                        break;
                    }
                }
                if ($remaining) {
                    $this->fail('Insufficient available stock for '.$product->name.'.');
                }
            }

            return $id;
        });
    }

    public function allocations(int $id)
    {
        return DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->join('batches as b', 'b.id', '=', 'a.batch_id')->where('i.order_id', $id)->select('a.*', 'b.reference', 'i.name', 'i.quantity as packs', 'i.pack_quantity', 'i.unit_price')->orderBy('a.id')->get();
    }

    public function transition(int $id, string $status, string $date): void
    {
        $this->transaction(function () use ($id, $status, $date) {
            $order = DB::table('orders')->find($id);
            if (! $order) {
                $this->fail('Order not found.');
            }
            if ($order->status === $status) {
                return;
            }
            $allowed = ['Pending' => ['Dispatched', 'Cancelled'], 'Dispatched' => ['Delivered', 'Cancelled'], 'Delivered' => ['Cancelled'], 'Cancelled' => []];
            if (! in_array($status, $allowed[$order->status] ?? [], true)) {
                $this->fail('Invalid fulfilment transition.');
            }
            if ($date < $order->ordered_on || $date > today()->toDateString() || ($status === 'Delivered' && $date < $order->dispatched_on)) {
                $this->fail('Fulfilment date must follow the order/dispatch date and cannot be in the future.');
            }
            $allocations = $this->allocations($id);
            if ($status === 'Dispatched') {
                $remainingRevenue = $order->subtotal - $order->discount;
                $last = $allocations->last()?->id;
                foreach ($allocations as $a) {
                    $b = DB::table('batches')->lockForUpdate()->find($a->batch_id);
                    if ($b->reserved < $a->quantity || $b->physical < $a->quantity) {
                        $this->fail('Stock changed; dispatch cannot proceed.');
                    }
                    $investment = $b->purchase + $b->packaging + $b->branding + $b->other;
                    $used = DB::table('allocations')->where('batch_id', $b->id)->where('dispatched', true)->where('returned', false)->sum('quantity');
                    $consumedCost = (int) DB::table('allocations')->where('batch_id', $b->id)->where('dispatched', true)->where('returned', false)->sum('cost');
                    $lostQuantity = max(0, $b->original_quantity - $b->physical - $used);
                    $lossCost = Money::allocate($investment, $lostQuantity, $b->original_quantity);
                    $remainingCost = max(0, $investment - $consumedCost - $lossCost);
                    $cost = (int) $b->physical === (int) $a->quantity ? $remainingCost : min($remainingCost, Money::allocate($investment, $a->quantity, $b->original_quantity));
                    $gross = Money::allocate($a->unit_price, $a->quantity, $a->pack_quantity);
                    $revenue = $a->id === $last ? $remainingRevenue : Money::allocate($gross, $order->subtotal - $order->discount, $order->subtotal);
                    $remainingRevenue -= $revenue;
                    DB::table('allocations')->where('id', $a->id)->update(['dispatched' => true, 'cost' => $cost, 'merchandise' => $revenue]);
                    DB::table('batches')->where('id', $b->id)->update(['physical' => $b->physical - $a->quantity, 'reserved' => $b->reserved - $a->quantity]);
                }
            }
            if ($status === 'Cancelled' && $order->status === 'Pending') {
                foreach ($allocations as $a) {
                    DB::table('batches')->where('id', $a->batch_id)->decrement('reserved', $a->quantity);
                }
            }
            $values = ['status' => $status, 'updated_at' => now()];
            if ($status === 'Dispatched') {
                $values['dispatched_on'] = $date;
            }
            if ($status === 'Delivered') {
                $values['delivered_on'] = $date;
            }
            DB::table('orders')->where('id', $id)->update($values);
        });
    }

    public function returnStock(int $id): void
    {
        $this->transaction(function () use ($id) {
            $order = DB::table('orders')->find($id);
            if (! $order || $order->status !== 'Cancelled' || ! $order->dispatched_on) {
                $this->fail('Only cancelled, dispatched orders can be returned.');
            }
            if ($order->returned_at) {
                return;
            }
            foreach ($this->allocations($id) as $a) {
                if ($a->returned) {
                    continue;
                }
                DB::table('batches')->where('id', $a->batch_id)->increment('physical', $a->quantity);
                DB::table('allocations')->where('id', $a->id)->update(['returned' => true]);
            }
            DB::table('orders')->where('id', $id)->update(['returned_at' => now()]);
        });
    }

    public function paid(int $id): int
    {
        return (int) DB::table('receipts')->where('order_id', $id)->selectRaw("COALESCE(SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END),0) as net")->value('net');
    }

    public function payment(int $id, string $kind, string $amount, string $date, string $reference, string $key): void
    {
        $this->transaction(function () use ($id, $kind, $amount, $date, $reference, $key) {
            if (DB::table('receipts')->where('request_key', $key)->exists()) {
                return;
            }
            $order = DB::table('orders')->find($id);
            $value = Money::parse($amount);
            if (! $order || ! in_array($kind, ['receipt', 'refund'], true) || ! $value) {
                $this->fail('Choose a valid positive payment.');
            }
            if ($date < $order->ordered_on || $date > today()->toDateString()) {
                $this->fail('Payment date must be between the order date and today.');
            }
            $paid = $this->paid($id);
            if ($kind === 'receipt' && ($order->status === 'Cancelled' || $value > $order->total - $paid)) {
                $this->fail('Receipt exceeds balance or order is cancelled.');
            }
            if ($kind === 'refund' && ($order->status !== 'Cancelled' || $value > $paid)) {
                $this->fail('Refund must be against a cancelled order and cannot exceed net receipts.');
            }
            DB::table('receipts')->insert(['order_id' => $id, 'kind' => $kind, 'amount' => $value, 'paid_on' => $date, 'reference' => $reference, 'request_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function adjust(int $batchId, int $quantity, string $reason): void
    {
        $this->transaction(function () use ($batchId, $quantity, $reason) {
            $b = DB::table('batches')->find($batchId);
            $dispatched = (int) DB::table('allocations')->where('batch_id', $batchId)->where('dispatched', true)->where('returned', false)->sum('quantity');
            if (! $b || ! $quantity || $b->physical + $quantity < $b->reserved || $b->physical + $quantity > $b->original_quantity - $dispatched) {
                $this->fail('Adjustment must preserve reservations and cannot exceed original quantity less net dispatched stock. Receive a new batch for new purchases.');
            }
            DB::table('batches')->where('id', $batchId)->increment('physical', $quantity);
            DB::table('stock_adjustments')->insert(['batch_id' => $batchId, 'quantity' => $quantity, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function issueInvoice(int $id, bool $correct = false): int
    {
        return $this->transaction(function () use ($id, $correct) {
            $latest = DB::table('invoices')->where('order_id', $id)->orderByDesc('version')->first();
            if ($latest && ! $correct) {
                return $latest->id;
            }
            $order = DB::table('orders')->find($id);
            if (! $order) {
                $this->fail('Order not found.');
            }
            $version = ($latest->version ?? 0) + 1;
            $snapshot = ['brand' => 'Hive’s Best', 'issued_on' => today()->toDateString(), 'order' => $order, 'items' => DB::table('order_items')->where('order_id', $id)->get(), 'paid' => $this->paid($id)];
            // Only customer-facing fields are persisted in the issued snapshot.
            $snapshot['order'] = array_intersect_key((array) $order, array_flip(['number', 'customer', 'phone', 'address', 'ordered_on', 'subtotal', 'discount', 'delivery', 'total', 'status']));

            return DB::table('invoices')->insertGetId(['order_id' => $id, 'version' => $version, 'number' => $order->number.'-I'.$version, 'snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
