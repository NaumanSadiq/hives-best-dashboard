<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchCorrections
{
    public function preview(int $id, array $input, string $reason, ?string $count = null): array
    {
        $b = DB::table('batches')->find($id);
        abort_unless($b, 404);
        try {
            $data = validator($input, [
                'reference' => ['required', 'string', 'max:100', Rule::unique('batches', 'reference')->ignore($id)],
                'stock_key' => 'required|exists:products,stock_key',
                'received_on' => 'required|date_format:Y-m-d|before_or_equal:today',
                'original_quantity' => 'required|integer|min:1|max:100000000',
                ...array_fill_keys(['purchase', 'packaging', 'branding', 'other'], ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/']),
            ])->validate();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($v, $k) => ['batch.'.$k => $v])->all());
        }
        validator(['batchReason' => $reason, 'stockCount' => $count], ['batchReason' => 'required|string|min:5|max:2000', 'stockCount' => 'nullable|integer|min:0|max:100000000'])->validate();
        foreach (['purchase', 'packaging', 'branding', 'other'] as $field) {
            $data[$field] = Money::parse($data[$field]);
        }
        $data['original_quantity'] = (int) $data['original_quantity'];
        if ($data['stock_key'] !== $b->stock_key && app(Operations::class)->batchUsed($id)) {
            throw ValidationException::withMessages(['batch.stock_key' => 'This pool has recorded allocations or movements. Keep it here; record a physical loss and receive a separate batch in the correct pool with a linked reason.']);
        }
        $allocations = DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->join('orders as o', 'o.id', '=', 'i.order_id')->where('a.batch_id', $id)->orderBy('a.id')->select('a.*', 'i.order_id', 'o.status', 'o.number', 'o.dispatched_on', 'o.returned_at')->get();
        $net = (int) $allocations->where('dispatched', true)->where('returned', false)->sum('quantity');
        $receivedDelta = $data['original_quantity'] - $b->original_quantity;
        $historicalPeak = $this->historicalPeak($allocations);
        if ($receivedDelta < 0 && $data['original_quantity'] < $historicalPeak) {
            throw ValidationException::withMessages(['batch.original_quantity' => "At least {$historicalPeak} base units were out on recorded dispatches before their returns. The corrected receipt cannot be smaller than that historical stock requirement."]);
        }
        $physical = $b->physical + $receivedDelta;
        if ($physical < $b->reserved) {
            $minimum = $b->original_quantity - $b->physical + $b->reserved;
            throw ValidationException::withMessages(['batch.original_quantity' => "Received quantity must be at least {$minimum}: {$net} net dispatched, ".($b->original_quantity - $b->physical - $net)." net lost, and {$b->reserved} reserved. This change would leave {$physical} on hand."]);
        }
        $countDelta = $count !== null && $count !== '' ? (int) $count - $physical : 0;
        $physical += $countDelta;
        if ($physical < $b->reserved || $physical > $data['original_quantity'] - $net) {
            throw ValidationException::withMessages(['stockCount' => "On-hand count must be between {$b->reserved} reserved and ".($data['original_quantity'] - $net).' received less net dispatched. Receive new purchases separately.']);
        }
        $data['physical'] = $physical;
        $oldInvestment = $b->purchase + $b->packaging + $b->branding + $b->other;
        $investment = $data['purchase'] + $data['packaging'] + $data['branding'] + $data['other'];
        $reprice = $investment !== $oldInvestment || $data['original_quantity'] !== $b->original_quantity;
        $running = 0;
        $costs = [];
        $profitDelta = 0;
        foreach ($allocations as $a) {
            $cost = (int) $a->cost;
            if ($a->dispatched && $reprice) {
                if ($a->returned) {
                    $cost = Money::allocate($investment, $a->quantity, $data['original_quantity']);
                } else {
                    $cost = Money::allocate($investment, $running + $a->quantity, $data['original_quantity']) - Money::allocate($investment, $running, $data['original_quantity']);
                    $running += $a->quantity;
                }
            }
            if ($a->status === 'Delivered' && ! $a->returned) {
                $profitDelta += $a->cost - $cost;
            }
            $costs[] = ['id' => $a->id, 'order' => $a->number, 'quantity' => $a->quantity, 'dispatched' => (bool) $a->dispatched, 'returned' => (bool) $a->returned, 'before' => (int) $a->cost, 'after' => $cost];
        }
        $consumed = array_sum(array_map(fn ($a) => $a['dispatched'] && ! $a['returned'] ? $a['after'] : 0, $costs));
        $loss = Money::allocate($investment, max(0, $data['original_quantity'] - $physical - $net), $data['original_quantity']);
        // Any final paisa belongs to consumed stock when no physical stock remains.
        if ($reprice && $physical === 0 && $net > 0) {
            for ($i = count($costs) - 1; $i >= 0; $i--) {
                if ($costs[$i]['dispatched'] && ! $costs[$i]['returned']) {
                    $extra = $investment - $loss - $consumed;
                    $costs[$i]['after'] += $extra;
                    if ($allocations[$i]->status === 'Delivered') {
                        $profitDelta -= $extra;
                    }
                    $consumed += $extra;
                    break;
                }
            }
        }
        if ($physical === 0) {
            $loss = max(0, $investment - $consumed);
        }
        $effects = ['received_movement' => $receivedDelta, 'physical_movement' => $countDelta, 'reserved' => (int) $b->reserved, 'available' => $physical - $b->reserved, 'investment_before' => $oldInvestment, 'investment_after' => $investment, 'delivered_profit_change' => $profitDelta, 'remaining_value' => max(0, $investment - $loss - $consumed), 'loss_cost' => $loss, 'allocations' => $costs];

        return ['before' => (array) $b, 'after' => $data, 'effects' => $effects, 'fingerprint' => hash('sha256', json_encode([$b, $allocations, $data, $reason, $count]))];
    }

    public function save(int $id, array $data, string $reason, ?string $count = null, ?string $key = null, ?string $fingerprint = null): void
    {
        app(Operations::class)->transaction(function () use ($id, $data, $reason, $count, $key, $fingerprint) {
            if ($key && DB::table('batch_changes')->where('request_key', $key)->exists()) {
                return;
            }
            DB::table('batches')->where('id', $id)->lockForUpdate()->first();
            $plan = $this->preview($id, $data, $reason, $count);
            if ($fingerprint && ! hash_equals($fingerprint, $plan['fingerprint'])) {
                throw ValidationException::withMessages(['batchReason' => 'The batch, allocations or form changed. Review a fresh preview before confirming.']);
            }
            $changed = collect($plan['after'])->contains(fn ($v, $k) => (string) $v !== (string) $plan['before'][$k]);
            if (! $changed) {
                return;
            }
            DB::table('batches')->where('id', $id)->update($plan['after'] + ['updated_at' => now()]);
            foreach ($plan['effects']['allocations'] as $a) {
                if ($a['before'] !== $a['after']) {
                    DB::table('allocations')->where('id', $a['id'])->update(['cost' => $a['after']]);
                }
            }
            $revision = DB::table('batch_changes')->insertGetId(['batch_id' => $id, 'user_id' => auth()->id(), 'kind' => 'correction', 'reason' => $reason, 'before' => json_encode($plan['before']), 'after' => json_encode($plan['after']), 'effects' => json_encode($plan['effects']), 'request_key' => $key, 'created_at' => now()]);
            foreach (['received_movement' => 'received_correction', 'physical_movement' => 'physical'] as $field => $kind) {
                if ($quantity = $plan['effects'][$field]) {
                    DB::table('stock_adjustments')->insert(['batch_id' => $id, 'batch_change_id' => $revision, 'kind' => $kind, 'quantity' => $quantity, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    private function historicalPeak($allocations): int
    {
        $movements = [];
        $events = DB::table('order_events')->whereIn('order_id', $allocations->pluck('order_id'))->whereIn('action', ['Dispatched', 'Stock returned'])->orderBy('id')->get();
        foreach ($allocations->where('dispatched', true)->groupBy('order_id') as $orderId => $group) {
            $order = $group->first();
            $dispatch = $events->where('order_id', $orderId)->firstWhere('action', 'Dispatched');
            $movements[] = [$dispatch->created_at ?? $order->dispatched_on.' 00:00:00', $dispatch->id ?? 0, (int) $group->sum('quantity')];
            if ($order->returned_at) {
                $return = $events->where('order_id', $orderId)->firstWhere('action', 'Stock returned');
                $movements[] = [$return->created_at ?? $order->returned_at, $return->id ?? PHP_INT_MAX, -(int) $group->where('returned', true)->sum('quantity')];
            }
        }
        usort($movements, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $out = $peak = 0;
        foreach ($movements as [, , $quantity]) {
            $out += $quantity;
            $peak = max($peak, $out);
        }

        return $peak;
    }
}
