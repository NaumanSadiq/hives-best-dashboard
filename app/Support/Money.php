<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function parse(mixed $value): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,9}(\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['operation' => 'Enter a positive PKR amount with up to two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int|float|null $value): string
    {
        return 'PKR '.number_format(($value ?? 0) / 100, 2);
    }

    public static function input(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }

    public static function allocate(int $amount, int $quantity, int $basis): int
    {
        if ($basis <= 0) {
            return 0;
        }
        $whole = intdiv($amount, $basis);
        $fraction = $amount % $basis;
        $result = 0;
        $remainder = 0;
        foreach (str_split(decbin($quantity)) as $bit) {
            $result *= 2;
            $remainder *= 2;
            if ($remainder >= $basis) {
                $result++;
                $remainder -= $basis;
            }
            if ($bit === '1') {
                $result += $whole;
                $remainder += $fraction;
                if ($remainder >= $basis) {
                    $result++;
                    $remainder -= $basis;
                }
            }
        }

        return $result;
    }
}
