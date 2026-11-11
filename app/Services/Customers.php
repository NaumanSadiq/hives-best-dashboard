<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Customers
{
    public static function phone(?string $phone): ?string
    {
        $raw = trim($phone ?? '');
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^0[1-9]\d{8,9}$/', $digits)) {
            $digits = '92'.substr($digits, 1);
        }
        if (preg_match('/^92[1-9]\d{8,9}$/', $digits)) {
            return $digits;
        }
        if ((str_starts_with($raw, '+') || str_starts_with($raw, '00')) && preg_match('/^[1-9]\d{9,14}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public static function name(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function identity(string $name, ?string $phone, string $fallback): string
    {
        return $phone ? 'match:'.hash('sha256', $phone.'|'.self::name($name)) : $fallback;
    }

    public function backfill(): array
    {
        return app(Operations::class)->transaction(function () {
            $all = DB::table('orders')->orderBy('created_at')->orderBy('id')->get();
            $names = [];
            foreach ($all as $o) {
                if ($phone = self::phone($o->phone)) {
                    $names[$phone][self::name($o->customer)] = true;
                }
            }
            $created = $linked = 0;
            foreach ($all->whereNull('customer_id')->groupBy(fn ($o) => $this->identity($o->customer, self::phone($o->phone), 'order:'.$o->id)) as $identity => $group) {
                $latest = $group->last();
                $phone = self::phone($latest->phone);
                $reason = ! $phone ? 'Missing or unrecognised phone; kept separate' : (count($names[$phone]) > 1 ? 'Shared phone with conflicting names; confirm identity' : null);
                $customer = DB::table('customers')->where('identity_key', $identity)->first();
                if (! $customer) {
                    $id = DB::table('customers')->insertGetId(['name' => $latest->customer, 'phone' => $latest->phone, 'address' => $latest->address, 'normalized_phone' => $phone, 'normalized_name' => self::name($latest->customer), 'identity_key' => $identity, 'review_reason' => $reason, 'source_order_id' => $latest->id, 'source' => 'Backfill: phone + normalized name; latest created order snapshot', 'created_at' => now(), 'updated_at' => now()]);
                    $created++;
                } else {
                    $id = $customer->id;
                }
                $linked += DB::table('orders')->whereIn('id', $group->pluck('id'))->whereNull('customer_id')->update(['customer_id' => $id]);
            }

            return ['created' => $created, 'linked' => $linked, 'review' => DB::table('customers')->whereNotNull('review_reason')->whereNull('reviewed_at')->get(['id', 'name', 'source_order_id', 'review_reason'])->all()];
        });
    }

    public function resolve(array $data, string $key, ?object $old = null): int
    {
        $selected = (int) (array_key_exists('customer_id', $data) ? $data['customer_id'] : ($old->customer_id ?? 0));
        $phone = self::phone($data['phone']);
        $name = self::name($data['customer']);
        if ($selected) {
            $customer = DB::table('customers')->find($selected);
            if (! $customer) {
                throw ValidationException::withMessages(['order.customer_id' => 'Choose an existing customer.']);
            }
        } else {
            $identity = $this->identity($data['customer'], $phone, 'request:'.$key);
            $customer = DB::table('customers')->where('identity_key', $identity)->first();
            $conflict = $phone && DB::table('customers')->where('normalized_phone', $phone)->where('normalized_name', '!=', $name)->exists();
            if (! $customer && $conflict && empty($data['confirm_distinct'])) {
                throw ValidationException::withMessages(['order.phone' => 'This phone belongs to another saved name. Select the returning customer, or explicitly confirm that this is a different person sharing the phone.']);
            }
            if (! $customer) {
                $id = DB::table('customers')->insertGetId(['name' => $data['customer'], 'phone' => $data['phone'], 'address' => $data['address'], 'normalized_phone' => $phone, 'normalized_name' => $name, 'identity_key' => $identity, 'review_reason' => $conflict ? 'Shared phone with conflicting names; confirm identity' : (! $phone ? 'Missing or unrecognised phone; kept separate' : null), 'source' => 'Created during order entry', 'created_at' => now(), 'updated_at' => now()]);
                $customer = DB::table('customers')->find($id);
                if ($conflict) {
                    DB::table('customers')->where('normalized_phone', $phone)->update(['review_reason' => 'Shared phone with conflicting names; confirm identity', 'reviewed_at' => null]);
                }
            }
        }
        if ($old && $old->customer_id && (int) $old->customer_id !== (int) $customer->id) {
            if (DB::table('receipts')->where('order_id', $old->id)->exists() || DB::table('invoices')->where('order_id', $old->id)->exists()) {
                throw ValidationException::withMessages(['order.customer_id' => 'This order has payment history or an issued invoice. Its customer link cannot change; receipts and balances must stay with that customer.']);
            }
            if (empty($data['confirm_customer_change'])) {
                throw ValidationException::withMessages(['order.customer_id' => 'Confirm reassignment of this unpaid order and its outstanding balance.']);
            }
        }
        if (! empty($data['save_customer'])) {
            $this->update($customer->id, ['name' => $data['customer'], 'phone' => $data['phone'], 'address' => $data['address']], false, 'Explicit profile update from order');
        }

        return $customer->id;
    }

    public function update(int $id, array $data, bool $reviewed = false, string $source = 'Customer profile edit'): void
    {
        app(Operations::class)->transaction(function () use ($id, $data, $reviewed, $source) {
            $old = DB::table('customers')->lockForUpdate()->find($id);
            abort_unless($old, 404);
            $data = validator($data, ['name' => 'required|string|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:2000'])->validate();
            $phone = self::phone($data['phone']);
            $identity = $this->identity($data['name'], $phone, 'customer:'.$id);
            if (DB::table('customers')->where('identity_key', $identity)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['profile.phone' => 'Another customer already has this name and normalized phone. Keep the profiles separate until their linked orders are reviewed.']);
            }
            $conflict = $phone && DB::table('customers')->where('normalized_phone', $phone)->where('id', '!=', $id)->exists();
            $data += ['normalized_phone' => $phone, 'normalized_name' => self::name($data['name']), 'identity_key' => $identity, 'review_reason' => $conflict ? 'Shared phone; confirm identity' : (! $phone ? 'Missing or unrecognised phone; kept separate' : null), 'reviewed_at' => $reviewed ? now() : null, 'updated_at' => now()];
            DB::table('customers')->where('id', $id)->update($data);
            DB::table('customer_changes')->insert(['customer_id' => $id, 'user_id' => auth()->id(), 'before' => json_encode($old), 'after' => json_encode(DB::table('customers')->find($id)), 'source' => $source, 'created_at' => now()]);
        });
    }

    public function listing(string $search = '')
    {
        $orders = DB::table('orders')->select('customer_id')->selectRaw("COUNT(*) total_orders, SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered_orders, SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled_orders, MAX(ordered_on) latest_order")->groupBy('customer_id');
        $payments = DB::table('receipts as r')->join('orders as o', 'o.id', '=', 'r.order_id')->select('o.customer_id')->selectRaw("SUM(CASE WHEN r.kind='receipt' THEN r.amount ELSE -r.amount END) net_paid")->groupBy('o.customer_id');
        $balances = (new Reports)->balances()->where('o.status', '!=', 'Cancelled')->select('o.customer_id')->selectRaw('SUM(CASE WHEN o.total>COALESCE(p.paid,0) THEN o.total-COALESCE(p.paid,0) ELSE 0 END) outstanding')->groupBy('o.customer_id');
        $query = DB::table('customers as c')->leftJoinSub($orders, 's', 's.customer_id', '=', 'c.id')->leftJoinSub($payments, 'r', 'r.customer_id', '=', 'c.id')->leftJoinSub($balances, 'b', 'b.customer_id', '=', 'c.id')->select('c.*', 's.latest_order')->selectRaw('COALESCE(s.total_orders,0) total_orders, COALESCE(s.delivered_orders,0) delivered_orders, COALESCE(s.cancelled_orders,0) cancelled_orders, COALESCE(r.net_paid,0) net_paid, COALESCE(b.outstanding,0) outstanding');
        if ($search !== '') {
            $digits = preg_match('/^[+\d\s().-]+$/', $search) ? preg_replace('/\D/', '', $search) : '';
            $phoneSearch = self::phone($search) ?? (str_starts_with($digits, '00') ? substr($digits, 2) : (str_starts_with($digits, '0') ? '92'.substr($digits, 1) : $digits));
            $query->where(fn ($q) => $q->where('c.name', 'like', '%'.$search.'%')->orWhere('c.phone', 'like', '%'.$search.'%')->when($digits !== '', fn ($q) => $q->orWhere('c.normalized_phone', 'like', '%'.$phoneSearch.'%')));
        }

        return $query;
    }

    public function rankings(string $from, string $to): array
    {
        $orders = DB::table('orders')->where('status', '!=', 'Cancelled')->whereBetween('ordered_on', [$from, $to])->select('customer_id')->selectRaw("COUNT(*) count, SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered")->groupBy('customer_id');
        $payments = DB::table('receipts as r')->join('orders as o', 'o.id', '=', 'r.order_id')->whereBetween('r.paid_on', [$from, $to])->select('o.customer_id')->selectRaw("SUM(CASE WHEN r.kind='receipt' THEN r.amount ELSE -r.amount END) net")->groupBy('o.customer_id');

        return ['orders' => DB::table('customers as c')->joinSub($orders, 's', 's.customer_id', '=', 'c.id')->select('c.id', 'c.name', 's.count', 's.delivered')->orderByDesc('s.count')->orderBy('c.id')->limit(5)->get(), 'payments' => DB::table('customers as c')->joinSub($payments, 'p', 'p.customer_id', '=', 'c.id')->where('p.net', '>', 0)->select('c.id', 'c.name', 'p.net')->orderByDesc('p.net')->orderBy('c.id')->limit(5)->get()];
    }
}
