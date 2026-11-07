<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCorrections
{
    public function preview(int $id, array $data, string $reason): array
    {
        $receipt = DB::table('receipts')->find($id);
        abort_unless($receipt, 404);
        $order = DB::table('orders')->find($receipt->order_id);
        validator(['paymentEdit' => $data, 'paymentReason' => $reason], [
            'paymentEdit.amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'paymentEdit.paid_on' => 'required|date_format:Y-m-d|after_or_equal:'.$order->ordered_on.'|before_or_equal:today',
            'paymentEdit.reference' => 'nullable|string|max:255',
            'paymentReason' => 'required|string|min:5|max:2000',
        ])->validate();
        $after = ['amount' => Money::parse($data['amount']), 'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? ''];
        $delta = ($after['amount'] - $receipt->amount) * ($receipt->kind === 'receipt' ? 1 : -1);
        $oldNet = app(Operations::class)->paid($receipt->order_id);
        $newNet = $oldNet + $delta;
        if ($newNet < 0 || $newNet > $order->total) {
            throw ValidationException::withMessages(['paymentEdit.amount' => 'This change would leave net payments of '.Money::format($newNet).'. They must stay between zero and the order total of '.Money::format($order->total).'. Check the other receipts and refunds first.']);
        }

        return ['before' => (array) $receipt, 'after' => $after, 'order' => $order->number, 'status' => $order->status, 'cash_change' => $delta, 'net_before' => $oldNet, 'net_after' => $newNet, 'due_after' => $order->status === 'Cancelled' ? $newNet : $order->total - $newNet, 'fingerprint' => hash('sha256', json_encode([$receipt, $after, $reason, $oldNet, $order->total, $order->status]))];
    }

    public function save(int $id, array $data, string $reason, string $key, string $fingerprint): void
    {
        app(Operations::class)->transaction(function () use ($id, $data, $reason, $key, $fingerprint) {
            if (DB::table('payment_changes')->where('request_key', $key)->exists()) {
                return;
            }
            DB::table('receipts')->where('id', $id)->lockForUpdate()->first();
            $plan = $this->preview($id, $data, $reason);
            if (! hash_equals($fingerprint, $plan['fingerprint'])) {
                throw ValidationException::withMessages(['paymentReason' => 'The payment, order balance or form changed. Review a fresh preview before confirming.']);
            }
            if (! collect($plan['after'])->contains(fn ($v, $k) => (string) $v !== (string) $plan['before'][$k])) {
                return;
            }
            DB::table('receipts')->where('id', $id)->update($plan['after'] + ['updated_at' => now()]);
            DB::table('payment_changes')->insert(['receipt_id' => $id, 'user_id' => auth()->id(), 'request_key' => $key, 'reason' => $reason, 'before' => json_encode($plan['before']), 'after' => json_encode($plan['after']), 'cash_change' => $plan['cash_change'], 'created_at' => now()]);
        });
    }
}
