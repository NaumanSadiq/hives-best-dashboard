<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Reports
{
    public function summary(string $from, string $to): array
    {
        $delivered = DB::table('orders')->where('status', 'Delivered')->whereBetween('delivered_on', [$from, $to]);
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

        return ['orders' => DB::table('orders')->whereBetween('ordered_on', [$from, $to])->count(), 'revenue' => $revenue, 'cost' => $cost, 'courier' => $courier, 'profit' => $revenue - $cost - $courier, 'cash' => $cash, 'pending' => $pending, 'refunds' => $refunds, 'delivery' => (int) (clone $delivered)->sum('delivery'), 'merchandise' => (int) (clone $delivered)->sum(DB::raw('subtotal-discount'))];
    }

    public function batches(string $from, string $to)
    {
        $sold = DB::table('allocations as a')->join('order_items as i', 'i.id', '=', 'a.order_item_id')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.status', 'Delivered')->whereBetween('o.delivered_on', [$from, $to])->groupBy('a.batch_id')->select('a.batch_id')->selectRaw('SUM(a.quantity) sold, SUM(a.cost) realised_cost, SUM(a.merchandise) revenue');
        $consumed = DB::table('allocations')->where('dispatched', true)->where('returned', false)->groupBy('batch_id')->select('batch_id')->selectRaw('SUM(cost) consumed_cost, SUM(quantity) dispatched_quantity');

        return DB::table('batches as b')->leftJoinSub($sold, 's', 's.batch_id', '=', 'b.id')->leftJoinSub($consumed, 'c', 'c.batch_id', '=', 'b.id')->select('b.*','s.sold','s.realised_cost','s.revenue','c.consumed_cost','c.dispatched_quantity')->orderByDesc('b.received_on')->paginate(12,pageName: 'batchesPage');
    }
}
