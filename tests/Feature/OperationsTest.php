<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Operations;
use App\Services\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private Operations $ops;

    private int $half;

    private int $full;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = new Operations;
        $this->half = DB::table('products')->insertGetId(['name' => 'Sidr Honey · 500 g', 'stock_key' => 'sidr', 'kind' => 'honey', 'pack_quantity' => 500, 'price' => 120000, 'low_stock' => 1000]);
        $this->full = DB::table('products')->insertGetId(['name' => 'Sidr Honey · 1 kg', 'stock_key' => 'sidr', 'kind' => 'honey', 'pack_quantity' => 1000, 'price' => 220000, 'low_stock' => 1000]);
        DB::table('batches')->insert(['reference' => 'TEST-01', 'stock_key' => 'sidr', 'received_on' => today()->subDays(3)->toDateString(), 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 1000000, 'packaging' => 100000, 'branding' => 50000, 'other' => 50000]);
    }

    private function data(): array
    {
        return ['customer' => 'QA Customer', 'phone' => '03000000000', 'address' => 'Disposable test address', 'ordered_on' => today()->toDateString(), 'discount' => '100', 'delivery' => '200', 'courier' => '150', 'tracking' => 'TEST', 'notes' => 'Private test note'];
    }

    private function order(?string $key = null): int
    {
        return $this->ops->saveOrder($this->data(), [['product_id' => $this->half, 'quantity' => 1, 'price' => '1200'], ['product_id' => $this->full, 'quantity' => 1, 'price' => '2200']], $key ?? (string) Str::uuid());
    }

    private function pay(int $id, string $amount, string $kind = 'receipt', ?string $key = null): void
    {
        $this->ops->payment($id, $kind, $amount, today()->toDateString(), 'QA transfer', $key ?? (string) Str::uuid());
    }

    public function test_complete_honey_flow_and_reconciliation(): void
    {
        $key = (string) Str::uuid();
        $id = $this->order($key);
        $this->assertSame($id, $this->order($key));
        $this->assertDatabaseHas('batches', ['reserved' => 1500, 'physical' => 10000]);
        $paymentKey = (string) Str::uuid();
        $this->pay($id, '1000', 'receipt', $paymentKey);
        $this->pay($id, '1000', 'receipt', $paymentKey);
        $this->assertSame(100000, $this->ops->paid($id));
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $this->assertDatabaseHas('batches', ['physical' => 8500, 'reserved' => 0]);
        $this->assertEquals(180000, DB::table('allocations')->sum('cost'));
        $this->assertEquals(330000, DB::table('allocations')->sum('merchandise'));
        $this->ops->transition($id, 'Delivered', today()->toDateString());
        $s = (new Reports)->summary(today()->toDateString(), today()->toDateString());
        $this->assertSame(350000, $s['revenue']);
        $this->assertSame(155000, $s['profit']);
        $this->assertSame(250000, $s['pending']);
        $this->assertSame(100000, $s['cash']);
        $this->pay($id, '2500');
        $this->assertSame(350000, $this->ops->paid($id));
        $invoice = $this->ops->issueInvoice($id);
        $original = DB::table('invoices')->find($invoice)->snapshot;
        DB::table('products')->where('id', $this->half)->update(['price' => 999999]);
        DB::table('orders')->where('id', $id)->update(['customer' => 'Changed customer']);
        $this->assertSame($invoice, $this->ops->issueInvoice($id));
        $this->assertSame($original, DB::table('invoices')->find($invoice)->snapshot);
        $corrected = $this->ops->issueInvoice($id, true);
        $this->assertNotSame($invoice, $corrected);
        $this->assertStringNotContainsString('Private test note', $original);
        $this->assertStringNotContainsString('courier', $original);
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin)->get(route('invoice', $invoice))->assertOk()->assertSee('Copy Invoice Image')->assertDontSee('Private test note');
    }

    public function test_pending_edits_release_reservation_and_cancel_preserves_receipts(): void
    {
        $id = $this->order();
        $this->pay($id, '500');
        $this->ops->saveOrder($this->data(), [['product_id' => $this->half, 'quantity' => 1, 'price' => '1200']], (string) Str::uuid(), $id);
        $this->assertDatabaseHas('batches', ['reserved' => 500, 'physical' => 10000]);
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->assertDatabaseHas('batches', ['reserved' => 0, 'physical' => 10000]);
        $s = (new Reports)->summary(today()->toDateString(), today()->toDateString());
        $this->assertSame(50000, $s['refunds']);
        $this->pay($id, '500', 'refund');
        $this->assertSame(0, $this->ops->paid($id));
        $this->assertSame(2, DB::table('receipts')->where('order_id', $id)->count());
    }

    public function test_dispatched_cancellation_requires_explicit_idempotent_return(): void
    {
        $id = $this->order();
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->assertDatabaseHas('batches', ['physical' => 8500]);
        $this->ops->returnStock($id);
        $this->ops->returnStock($id);
        $this->assertDatabaseHas('batches', ['physical' => 10000, 'reserved' => 0]);
        $this->assertEquals(1500, DB::table('allocations')->where('returned', true)->sum('quantity'));
    }

    public function test_insufficient_stock_rolls_back_without_overselling(): void
    {
        $this->order();
        try {
            $this->ops->saveOrder($this->data(), [['product_id' => $this->full, 'quantity' => 10, 'price' => '2200']], (string) Str::uuid());
            $this->fail('Oversold stock.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertDatabaseHas('batches', ['physical' => 10000, 'reserved' => 1500]);
    }

    public function test_fifo_spans_batches_and_costs_reconcile(): void
    {
        DB::table('batches')->where('reference', 'TEST-01')->update(['original_quantity' => 600, 'physical' => 600]);
        DB::table('batches')->insert(['reference' => 'TEST-02', 'stock_key' => 'sidr', 'received_on' => today()->toDateString(), 'original_quantity' => 2000, 'physical' => 2000, 'reserved' => 0, 'purchase' => 200000, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
        $id = $this->order();
        $a = $this->ops->allocations($id);
        $this->assertCount(3, $a);
        $this->assertEquals(600, $a->where('reference', 'TEST-01')->sum('quantity'));
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $this->ops->transition($id, 'Delivered', today()->toDateString());
        $this->assertEquals(1290000, DB::table('allocations')->sum('cost'));
        $this->assertEquals(330000, DB::table('allocations')->sum('merchandise'));
        $batches = (new Reports)->batches(today()->toDateString(), today()->toDateString());
        $this->assertEquals(330000, $batches->sum('revenue'));
    }

    public function test_adjustments_preserve_reservations_and_record_reason(): void
    {
        $this->order();
        $batch = DB::table('batches')->value('id');
        $this->ops->adjust($batch, -100, 'Damaged container');
        $this->assertDatabaseHas('stock_adjustments', ['quantity' => -100, 'reason' => 'Damaged container']);
        $this->expectException(ValidationException::class);
        $this->ops->adjust($batch, -9000, 'Would consume reserved stock');
    }

    public function test_protected_pages_and_livewire_mutations(): void
    {
        foreach (['/', '/orders', '/stock', '/reports', '/invoices/1'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/register')->assertNotFound();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertForbidden();
        Livewire::actingAs($user)->test(Admin::class)->assertForbidden();
        $user->is_admin = true;
        $user->save();
        foreach (['/', '/orders', '/stock'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        Livewire::actingAs($user)->test(Admin::class, ['page' => 'orders'])->call('newOrder')->set('order.customer', 'Livewire QA')->set('order.phone', '03000000000')->set('order.address', 'Test address')->set('lines', [['product_id' => $this->half, 'quantity' => 1, 'price' => '1200']])->call('saveOrder')->assertHasNoErrors();
        $this->assertDatabaseHas('orders', ['customer' => 'Livewire QA']);
    }

    public function test_invalid_payment_and_transition_are_rejected(): void
    {
        $id = $this->order();
        try {
            $this->pay($id, '4000');
            $this->fail('Overpayment accepted');
        } catch (ValidationException) {
            $this->assertSame(0, $this->ops->paid($id));
        }
        $this->expectException(ValidationException::class);
        $this->ops->transition($id, 'Delivered', today()->toDateString());
    }

    public function test_final_dispatch_reconciles_fractional_cost_after_return(): void
    {
        DB::table('batches')->where('reference', 'TEST-01')->update(['original_quantity' => 1500, 'physical' => 1500, 'purchase' => 1, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
        $id = $this->order();
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $this->assertEquals(1, DB::table('allocations')->sum('cost'));
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->ops->returnStock($id);
        $other = $this->order();
        $this->ops->transition($other, 'Dispatched', today()->toDateString());
        $this->assertEquals(1, DB::table('allocations')->where('returned', false)->sum('cost'));
        $this->expectException(ValidationException::class);
        $this->ops->adjust(DB::table('batches')->value('id'), 500, 'Cannot restore sold quantity');
    }

    public function test_admin_setup_is_secure_and_single_use(): void
    {
        $password = Str::password(24);
        $this->artisan('hive:admin')
            ->expectsQuestion('Admin name', 'Test Admin')
            ->expectsQuestion('Admin email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertExitCode(0);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'is_admin' => true]);
        $this->assertTrue(Hash::check($password, DB::table('users')->value('password')));
        $this->artisan('hive:admin')->assertExitCode(1);
        $this->assertSame(1, DB::table('users')->where('is_admin', true)->count());
    }

    public function test_report_dates_and_post_dispatch_courier_updates(): void
    {
        $ordered = today()->subDays(3)->toDateString();
        $delivered = today()->subDay()->toDateString();
        $data = $this->data();
        $data['ordered_on'] = $ordered;
        $id = $this->ops->saveOrder($data, [['product_id' => $this->half, 'quantity' => 1, 'price' => '1200'], ['product_id' => $this->full, 'quantity' => 1, 'price' => '2200']], (string) Str::uuid());
        $this->ops->payment($id, 'receipt', '1000', $ordered, 'Deposit', (string) Str::uuid());
        $this->ops->transition($id, 'Dispatched', $ordered);
        $this->ops->transition($id, 'Delivered', $delivered);
        $this->pay($id, '2500');
        $reports = new Reports;
        $this->assertSame(0, $reports->summary($ordered, $ordered)['revenue']);
        $this->assertSame(100000, $reports->summary($ordered, $ordered)['cash']);
        $this->assertSame(350000, $reports->summary($delivered, $delivered)['revenue']);
        $this->assertSame(0, $reports->summary($delivered, $delivered)['cash']);
        $this->assertSame(250000, $reports->summary(today()->toDateString(), today()->toDateString())['cash']);
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        Livewire::actingAs($admin)->test(Admin::class)->call('editAmounts', $id)->set('amountEdit.courier', '175.25')->set('amountReason', 'Correct courier charge')->call('previewAmounts')->call('saveAmounts')->assertHasNoErrors()->set('trackingInput', 'UPDATED-TRACK')->call('saveOperationalDetails')->assertHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $id, 'courier' => 17525, 'tracking' => 'UPDATED-TRACK']);
        $this->assertSame(152475, $reports->summary($delivered, $delivered)['profit']);
    }
}
