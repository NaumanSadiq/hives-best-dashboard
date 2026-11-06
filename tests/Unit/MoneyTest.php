<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_exact_minor_units_and_overflow_safe_allocation(): void
    {
        $this->assertSame(100010, Money::parse('1000.10'));
        $this->assertSame(1, Money::parse('0.01'));
        $this->assertSame(99999999900, Money::parse('999999999'));
        $this->assertSame(33333333333333, Money::allocate(100000000000000, 1, 3));
        $this->assertSame(33333333333333, Money::allocate(100000000000000, 100000000, 300000000));
        $this->assertSame(0, Money::allocate(1234, 0, 0));
        for ($amount = 0; $amount < 100; $amount += 7) {
            for ($quantity = 0; $quantity < 50; $quantity += 3) {
                $this->assertSame(intdiv($amount * $quantity, 17), Money::allocate($amount,$quantity,17));
            }
        }
    }
}
