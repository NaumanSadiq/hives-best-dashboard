<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Operations;
use App\Services\PaymentCorrections;
use App\Services\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialClarityTest extends TestCase
{
    use RefreshDatabase;

    private Operations $ops;

    private int $full;

    private int $half;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(12, 0));
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $this->ops = new Operations;
        $this->full = DB::table('products')->insertGetId(['name' => 'Honey large', 'kind' => 'honey', 'stock_key' => 'honey', 'pack_quantity' => 1000, 'price' => 400000, 'low_stock' => 0]);
        $this->half = DB::table('products')->insertGetId(['name' => 'Honey small', 'kind' => 'honey', 'stock_key' => 'honey', 'pack_quantity' => 500, 'price' => 200000, 'low_stock' => 0]);
        DB::table('batches')->insert(['reference' => 'Financial fixture', 'stock_key' => 'honey', 'received_on' => '2026-11-01', 'original_quantity' => 100000, 'physical' => 100000, 'purchase' => 10000000, 'packaging' => 1000000, 'branding' => 1000000, 'other' => 0]);
    }

    private function data(string $delivery = '0'): array
    {
        return ['customer' => 'Disposable financial customer', 'phone' => '000', 'address' => 'QA', 'ordered_on' => '2026-11-07', 'discount' => '0', 'delivery' => $delivery, 'courier' => '0', 'notes' => '', 'tracking' => ''];
    }

    private function order(array $lines = [], string $delivery = '0'): int
    {
        return $this->ops->saveOrder($this->data($delivery), $lines ?: [['product_id' => $this->full, 'quantity' => 2, 'price' => '4000']], (string) Str::uuid());
    }

    private function delivered(int $id): void
    {
        $this->ops->transition($id, 'Dispatched', '2026-11-07');
        $this->ops->transition($id, 'Delivered', '2026-11-07');
    }

    public function test_cancel_edit_recreate_retry_and_fulfilment_do_not_copy_payments(): void
    {
        $old = $this->order(delivery: '250');
        $key = (string) Str::uuid();
        $this->ops->payment($old, 'receipt', '4250', '2026-11-07', '', $key);
        $this->ops->payment($old, 'receipt', '4250', '2026-11-07', '', $key);
        $this->ops->saveOrder($this->data('250'), [['product_id' => $this->full, 'quantity' => 2, 'price' => '4000']], (string) Str::uuid(), $old);
        $this->assertDatabaseCount('receipts', 1);
        $this->ops->transition($old, 'Cancelled', '2026-11-07');
        $this->ops->transition($old, 'Cancelled', '2026-11-07');
        $new = $this->order(delivery: '1400');
        $this->delivered($new);
        $this->ops->issueInvoice($new);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame(0, $this->ops->paid($new));
        // A receipt appears on the new order only after an explicit payment submission.
        $component = Livewire::test(Admin::class, ['page' => 'orders'])->call('viewOrder', $new)->set('amount', '4250')->set('paidOn', '2026-11-07');
        $newKey = $component->get('paymentKey');
        $component->call('recordPayment')->assertHasNoErrors();
        $component->set('paymentKey', $newKey)->set('amount', '4250')->call('recordPayment')->assertHasNoErrors();
        $this->assertDatabaseCount('receipts', 2);
        $component->set('amount', '250')->call('recordPayment')->assertHasNoErrors();
        $baseline = $this->order([['product_id' => $this->full, 'quantity' => 1, 'price' => '29800']]);
        $this->delivered($baseline);
        $this->ops->payment($baseline, 'receipt', '29800', '2026-11-08', 'Fixture baseline', (string) Str::uuid());
        $s = (new Reports)->summary('0001-01-01', '9999-12-31');
        $this->assertSame(3855000, $s['cash']);
        $this->assertSame(425000, $s['cancelled_cash']);
        $this->assertSame(3430000, $s['cash'] - $s['cancelled_cash']);
        $this->assertSame(0, $s['refunded_cash']);
        $this->assertSame(450000, $this->ops->paid($new));
    }

    public function test_cash_breakdown_sums_all_pages_without_join_inflation_and_uses_payment_dates(): void
    {
        $order = $this->order();
        for ($i = 0; $i < 13; $i++) {
            $this->ops->payment($order, 'receipt', '100', $i < 12 ? '2026-11-07' : '2026-11-08', 'Distinct payment '.$i, (string) Str::uuid());
        }
        $this->ops->transition($order, 'Cancelled', '2026-11-08');
        $this->ops->payment($order, 'refund', '200', '2026-11-08', 'Actual return', (string) Str::uuid());
        $reports = new Reports;
        $s = $reports->summary('0001-01-01', '9999-12-31');
        $this->assertSame(110000, $s['cash']);
        $this->assertSame(20000, $s['refunded_cash']);
        $this->assertSame(120000, $reports->summary('2026-11-07', '2026-11-07')['cash']);
        $this->assertSame(-10000, $reports->summary('2026-11-08', '2026-11-08')['cash']);
        $all = $reports->paymentEntries('0001-01-01', '9999-12-31')->get();
        $this->assertCount(14, $all);
        $this->assertEquals($s['cash'], $all->sum(fn ($r) => $r->kind === 'receipt' ? $r->amount : -$r->amount));
        Livewire::test(Admin::class)->call('showBreakdown', 'cash')->assertSee('PKR 1,100.00')->assertSee('Total across all pages')->call('setPage', 2, 'detailPage')->assertSee('PKR 1,100.00')->assertSee('This page:');
    }

    public function test_delivered_pack_counts_use_saved_category_and_size_and_exclude_returns(): void
    {
        $mixed = $this->order([['product_id' => $this->full, 'quantity' => 1, 'price' => '4000'], ['product_id' => $this->half, 'quantity' => 1, 'price' => '2000']]);
        $this->delivered($mixed);
        $returned = $this->order();
        $this->delivered($returned);
        $this->ops->transition($returned, 'Cancelled', '2026-11-08');
        $this->ops->returnStock($returned);
        $pending = $this->order();
        $this->ops->payment($pending, 'receipt', '500', '2026-11-08', 'Advance', (string) Str::uuid());
        DB::table('products')->where('id', $this->full)->update(['kind' => 'snack', 'pack_quantity' => 1, 'name' => 'Changed settings']);
        $p = (new Reports)->packs('0001-01-01', '9999-12-31');
        $this->assertSame(1, $p['orders']);
        $this->assertSame(1, $p['kg_packs']);
        $this->assertSame(1, $p['half_packs']);
        $this->assertSame(1500, $p['grams']);
        $this->assertSame(0, $p['snacks']);
        $s = (new Reports)->summary('0001-01-01', '9999-12-31');
        $this->assertSame(600000, $s['revenue']);
        $this->assertSame(180000, $s['cost']);
        $this->assertSame(420000, $s['profit']);
        $this->assertSame(50000, $s['advance_cash']);
        $this->assertSame(0, (new Reports)->packs('2026-11-08', '2026-11-08')['orders']);
    }

    public function test_payment_correction_is_audited_idempotent_and_keeps_invoice_snapshot(): void
    {
        $order = $this->order();
        $this->ops->payment($order, 'receipt', '4250', '2026-11-07', '', (string) Str::uuid());
        $receipt = DB::table('receipts')->first();
        $invoice = $this->ops->issueInvoice($order);
        $snapshot = DB::table('invoices')->find($invoice)->snapshot;
        $this->ops->transition($order, 'Cancelled', '2026-11-08');
        $service = new PaymentCorrections;
        $data = ['amount' => '0', 'paid_on' => '2026-11-07', 'reference' => 'Confirmed duplicate fixture'];
        $reason = 'Disposable evidence identifies duplicate entry';
        $key = (string) Str::uuid();
        $plan = $service->preview($receipt->id, $data, $reason);
        $service->save($receipt->id, $data, $reason, $key, $plan['fingerprint']);
        $service->save($receipt->id, $data, $reason, $key, $plan['fingerprint']);
        $this->assertDatabaseCount('payment_changes', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame(0, $this->ops->paid($order));
        $this->assertSame($snapshot, DB::table('invoices')->find($invoice)->snapshot);
        $revision = DB::table('payment_changes')->first();
        $this->assertEquals(425000, json_decode($revision->before, true)['amount']);
        $this->assertEquals(-425000, $revision->cash_change);
        $this->assertEquals(auth()->id(), $revision->user_id);
        Livewire::test(Admin::class)->call('editPayment', $receipt->id)->assertSee('Payment correction history')->assertSee($reason)->assertSee('PKR 4,250.00');
    }

    public function test_other_honey_sizes_and_snacks_are_separate_without_name_matching(): void
    {
        $quarter = DB::table('products')->insertGetId(['name' => 'Small jar', 'kind' => 'honey', 'stock_key' => 'honey', 'pack_quantity' => 250, 'price' => 100000, 'low_stock' => 0]);
        $snack = DB::table('products')->insertGetId(['name' => 'Honey trail mix', 'kind' => 'snack', 'stock_key' => 'snacks', 'pack_quantity' => 1, 'price' => 50000, 'low_stock' => 0]);
        DB::table('batches')->insert(['reference' => 'Snack fixture', 'stock_key' => 'snacks', 'received_on' => '2026-11-01', 'original_quantity' => 100, 'physical' => 100, 'purchase' => 100000]);
        $id = $this->order([['product_id' => $this->full, 'quantity' => 1, 'price' => '4000'], ['product_id' => $quarter, 'quantity' => 3, 'price' => '1000'], ['product_id' => $snack, 'quantity' => 2, 'price' => '500']]);
        $this->delivered($id);
        $packs = (new Reports)->packs('0001-01-01', '9999-12-31');
        $this->assertSame(1750, $packs['grams']);
        $this->assertSame(2, $packs['snacks']);
        $this->assertSame(4, $packs['honey_packs']);
        $this->assertEquals(3, $packs['other']->first()->packs);
        Livewire::test(Admin::class)->assertSee('Other honey sizes:')->assertSee('Snacks, shown separately:')->call('showBreakdown', 'packs')->assertSee('Honey trail mix')->assertSee('separate from honey weight');
    }

    public function test_payment_correction_rejects_missing_reason_stale_preview_and_impossible_net(): void
    {
        $order = $this->order();
        $this->ops->payment($order, 'receipt', '1000', '2026-11-07', '', (string) Str::uuid());
        $id = DB::table('receipts')->value('id');
        Livewire::test(Admin::class)->call('editPayment', $id)->set('paymentEdit.amount', '500')->call('previewPayment')->assertHasErrors('paymentReason');
        $service = new PaymentCorrections;
        $data = ['amount' => '500', 'paid_on' => '2026-11-07', 'reference' => ''];
        $plan = $service->preview($id, $data, 'Correct fixture receipt');
        $this->ops->payment($order, 'receipt', '100', '2026-11-07', '', (string) Str::uuid());
        try {
            $service->save($id, $data, 'Correct fixture receipt', (string) Str::uuid(), $plan['fingerprint']);
            $this->fail('Stale preview accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paymentReason', $e->errors());
        }
        $data['amount'] = '9000';
        try {
            $service->preview($id, $data, 'Wrong amount fixture');
            $this->fail('Overpayment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paymentEdit.amount', $e->errors());
        }
        $this->assertDatabaseCount('payment_changes', 0);
    }

    public function test_gross_profit_loss_and_admin_only_breakdowns(): void
    {
        $order = $this->order([['product_id' => $this->full, 'quantity' => 1, 'price' => '100']]);
        $this->delivered($order);
        Livewire::test(Admin::class)->assertSee('Loss — costs exceed delivered sales')->assertSee('Based on delivered sales, including amounts customers still owe.')->call('showBreakdown', 'profit')->assertSee('PKR -1,100.00');
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Admin::class)->assertForbidden();
    }
}
