<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Savings
{
    public function state(): array
    {
        return ['balance' => (int) DB::table('savings_entries')->sum('amount'), 'revision' => (int) DB::table('savings_entries')->max('id')];
    }

    public function preview(array $data, array $expected): array
    {
        validator($data, ['type' => 'required|in:opening,add,purchase,count,reversal', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'note' => 'required|string|min:3|max:2000'])->validate();
        $state = $this->state();
        if ($state !== $expected) {
            throw ValidationException::withMessages(['operation' => 'The savings balance changed. Reopen the action and review the latest balance.']);
        }
        if (($data['type'] === 'opening') !== ($state['revision'] === 0)) {
            throw ValidationException::withMessages(['operation' => 'Record one opening balance before adding other movements.']);
        }
        $reverses = null;
        if ($data['type'] === 'reversal') {
            $entry = DB::table('savings_entries')->find($data['entry'] ?? 0);
            if (! $entry || $entry->type === 'reversal' || DB::table('savings_entries')->where('reverses_id', $entry->id)->exists()) {
                throw ValidationException::withMessages(['operation' => 'Choose an entry that has not already been reversed.']);
            }
            $change = -$entry->amount;
            $reverses = $entry->id;
        } else {
            $amount = Money::parse($data['amount'] ?? '');
            if ($amount === 0 && ! in_array($data['type'], ['count', 'opening'])) {
                throw ValidationException::withMessages(['operation' => 'Enter a positive transaction amount.']);
            }
            $change = match ($data['type']) {
                'purchase' => -$amount, 'count' => $amount - $state['balance'], default => $amount
            };
            if ($data['type'] === 'count' && $change === 0) {
                throw ValidationException::withMessages(['operation' => 'The counted balance is unchanged.']);
            }
        }
        if ($state['balance'] + $change < 0) {
            throw ValidationException::withMessages(['operation' => 'This movement would spend below zero.']);
        }

        return ['state' => $state, 'type' => $data['type'], 'amount' => $change, 'after' => $state['balance'] + $change, 'date' => $data['date'], 'note' => $data['note'], 'reverses' => $reverses];
    }

    public function save(array $data, string $key, array $review): void
    {
        app(Operations::class)->transaction(function () use ($data, $key, $review) {
            if (DB::table('savings_entries')->where('request_key', $key)->exists()) {
                return;
            }
            $preview = $this->preview($data, $review['state'] ?? []);
            if ($preview !== $review) {
                throw ValidationException::withMessages(['operation' => 'Movement changed after preview. Review again before confirming.']);
            }
            DB::table('savings_entries')->insert(['user_id' => auth()->id(), 'request_key' => $key, 'type' => $preview['type'], 'amount' => $preview['amount'], 'before_balance' => $preview['state']['balance'], 'after_balance' => $preview['after'], 'business_date' => $preview['date'], 'explanation' => $preview['note'], 'reverses_id' => $preview['reverses'], 'created_at' => now()]);
        });
    }
}
