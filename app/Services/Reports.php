<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Reports
{
    public function summary(string $from, string $to): array
    {
        $delivered = $this->deliveredOrders($from, $to);
        $revenue = (int) (clone $delivered)->sum('total');
        $cost = (int) DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->whereIn('i.order_id', (clone $delivered)->select('id'))->sum('a.cost');
        $courier = (int) (clone $delivered)->sum('courier');
        $cash = (int) DB::table('receipts')->whereBetween('paid_on', [$from, $to])->selectRaw("COALESCE(SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END),0) as net")->value('net');
        $pending = 0;
        $refunds = 0;
        $payments = DB::table('receipts')->select('order_id')->selectRaw("SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END) net")->groupBy('order_id');
        foreach (DB::table('orders as o')->leftJoinSub($payments, 'p', 'p.order_id', '=', 'o.id')->select('o.total', 'o.status', 'p.net')->get() as $o) {
            if ($o->status === 'Cancelled') {
                $refunds += max(0, (int) $o->net);
            } else {
                $pending += max(0, $o->total - (int) $o->net);
            }
        }

        $extras = [];
        foreach (['purchase', 'packaging', 'branding', 'other'] as $field) {
            $extras['investment_'.$field] = (int) DB::table('batches')->whereBetween('received_on', [$from, $to])->sum($field);
        }
        $active = $this->balances()->where('o.status', '!=', 'Cancelled')->get();
        $extras['active_total'] = (int) $active->sum('total');
        $extras['active_paid'] = (int) $active->sum('paid');
        foreach (['Delivered' => 'delivered_cash', 'Cancelled' => 'cancelled_cash'] as $status => $key) {
            $extras[$key] = (int) $this->paymentEntries($from, $to)->where('o.status', $status)->sum(DB::raw("CASE WHEN r.kind='receipt' THEN r.amount ELSE -r.amount END"));
        }
        $extras['advance_cash'] = $cash - $extras['delivered_cash'] - $extras['cancelled_cash'];

        return $extras + ['orders' => DB::table('orders')->whereBetween('ordered_on', [$from, $to])->count(), 'revenue' => $revenue, 'cost' => $cost, 'courier' => $courier, 'profit' => $revenue - $cost - $courier, 'cash' => $cash, 'pending' => $pending, 'refunds' => $refunds, 'investment' => (int) DB::table('batches')->whereBetween('received_on', [$from, $to])->sum(DB::raw('purchase+packaging+branding+other')), 'discounts' => (int) (clone $delivered)->sum('discount'), 'subtotal' => (int) (clone $delivered)->sum('subtotal'), 'received_cash' => (int) DB::table('receipts')->where('kind', 'receipt')->whereBetween('paid_on', [$from, $to])->sum('amount'), 'refunded_cash' => (int) DB::table('receipts')->where('kind', 'refund')->whereBetween('paid_on', [$from, $to])->sum('amount'), 'delivery' => (int) (clone $delivered)->sum('delivery'), 'merchandise' => (int) (clone $delivered)->sum(DB::raw('subtotal-discount'))];
    }

    public function batches(string $from, string $to)
    {
        $sold = DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.status', 'Delivered')->whereBetween('o.delivered_on', [$from, $to])->groupBy('a.batch_id')->select('a.batch_id')->selectRaw('SUM(a.quantity) sold, SUM(a.cost) realised_cost, SUM(a.merchandise) revenue');
        $consumed = DB::table('allocations')->where('dispatched', true)->where('returned', false)->groupBy('batch_id')->select('batch_id')->selectRaw('SUM(cost) consumed_cost, SUM(quantity) dispatched_quantity');

        return DB::table('batches as b')->leftJoinSub($sold, 's', 's.batch_id', '=', 'b.id')->leftJoinSub($consumed, 'c', 'c.batch_id', '=', 'b.id')->select('b.*', 's.sold', 's.realised_cost', 's.revenue', 'c.consumed_cost', 'c.dispatched_quantity')->orderByDesc('b.received_on')->orderByDesc('b.id')->paginate(6, pageName: 'batchesPage');
    }

    public function deliveredOrders(string $from, string $to)
    {
        return DB::table('orders')->where('status', 'Delivered')->whereNull('returned_at')->whereBetween('delivered_on', [$from, $to]);
    }

    public function paymentEntries(string $from, string $to)
    {
        return DB::table('receipts as r')->join('orders as o', 'o.id', '=', 'r.order_id')->whereBetween('r.paid_on', [$from, $to])->select('r.*', 'o.number', 'o.status');
    }

    public function balances()
    {
        $paid = DB::table('receipts')->select('order_id')->selectRaw("SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END) paid")->groupBy('order_id');

        return DB::table('orders as o')->leftJoinSub($paid, 'p', 'p.order_id', '=', 'o.id')->select('o.*')->selectRaw('COALESCE(p.paid,0) paid, CASE WHEN o.total>COALESCE(p.paid,0) THEN o.total-COALESCE(p.paid,0) ELSE 0 END balance');
    }

    public function packLines(string $from, string $to)
    {
        return DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->join('products as p', 'p.id', '=', 'i.product_id')->whereIn('o.id', $this->deliveredOrders($from, $to)->select('id'))->select('i.*', 'o.number', 'o.delivered_on')->selectRaw('COALESCE(i.kind,p.kind) category');
    }

    public function packs(string $from, string $to): array
    {
        $groups = $this->packLines($from, $to)->select(['i.pack_quantity'])->selectRaw('COALESCE(i.kind,p.kind) group_kind, SUM(i.quantity) packs')->groupByRaw('COALESCE(i.kind,p.kind), i.pack_quantity')->get();
        $honey = $groups->where('group_kind', 'honey');

        return ['orders' => $this->deliveredOrders($from, $to)->count(), 'kg_packs' => (int) $honey->where('pack_quantity', 1000)->sum('packs'), 'half_packs' => (int) $honey->where('pack_quantity', 500)->sum('packs'), 'honey_packs' => (int) $honey->sum('packs'), 'grams' => (int) $honey->sum(fn ($g) => $g->pack_quantity * $g->packs), 'other' => $honey->whereNotIn('pack_quantity', [500, 1000])->values(), 'snacks' => (int) $groups->where('group_kind', 'snack')->sum('packs')];
    }

    public function breakdown(string $kind, string $from, string $to)
    {
        if ($kind === 'cash') {
            return $this->paymentEntries($from, $to)->orderByDesc('r.paid_on')->orderByDesc('r.id')->paginate(10, pageName: 'detailPage');
        }
        if ($kind === 'pending') {
            return $this->balances()->where('o.status', '!=', 'Cancelled')->whereRaw('o.total>COALESCE(p.paid,0)')->orderBy('o.id')->paginate(10, pageName: 'detailPage');
        }
        if ($kind === 'investment') {
            return DB::table('batches')->whereBetween('received_on', [$from, $to])->orderByDesc('received_on')->orderByDesc('id')->paginate(10, pageName: 'detailPage');
        }
        if ($kind === 'packs') {
            return $this->packLines($from, $to)->orderByDesc('o.delivered_on')->orderByDesc('i.id')->paginate(10, pageName: 'detailPage');
        }
        $costs = DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->select('i.order_id')->selectRaw('SUM(a.cost) allocated_cost')->groupBy('i.order_id');

        return $this->deliveredOrders($from, $to)->leftJoinSub($costs,'c','c.order_id','=','orders.id')->select('orders.*')->selectRaw('COALESCE(c.allocated_cost,0) allocated_cost')->orderByDesc('delivered_on')->orderByDesc('orders.id')->paginate(10,pageName: 'detailPage');
    }
}
