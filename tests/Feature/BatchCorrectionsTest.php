<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\BatchCorrections;
use App\Services\Operations;
use App\Services\Reports;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BatchCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private int $batch;

    private int $product;

    private Operations $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $this->ops = new Operations;
        $this->product = DB::table('products')->insertGetId(['name' => 'Correction honey', 'kind' => 'honey', 'stock_key' => 'correction-honey', 'pack_quantity' => 333, 'price' => 100000, 'low_stock' => 0]);
        $this->batch = DB::table('batches')->insertGetId(['reference' => 'Correction batch', 'stock_key' => 'correction-honey', 'received_on' => '2026-09-01', 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 200000, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
    }

    private function order(string $status = 'Pending'): int
    {
        $id = $this->ops->saveOrder(['customer' => 'Correction test', 'phone' => '0', 'address' => 'QA', 'ordered_on' => '2026-10-01', 'discount' => '0', 'delivery' => '0', 'courier' => '0'], [['product_id' => $this->product, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
        if ($status !== 'Pending') {
            $this->ops->transition($id, 'Dispatched', '2026-10-02');
        }
        if ($status === 'Delivered') {
            $this->ops->transition($id, 'Delivered', '2026-10-03');
        }

        return $id;
    }

    private function data(): array
    {
        $data = (array) DB::table('batches')->find($this->batch);
        foreach (['purchase', 'packaging', 'branding', 'other'] as $f) {
            $data[$f] = Money::input($data[$f]);
        }

        return $data;
    }

    public function test_cost_correction_restates_profit_preserves_returns_reservations_and_invoice_once(): void
    {
        $delivered = $this->order('Delivered');
        $dispatched = $this->order('Dispatched');
        $returned = $this->order('Dispatched');
        $pending = $this->order();
        $this->ops->transition($returned, 'Cancelled', '2026-10-03');
        $this->ops->returnStock($returned);
        $invoice = $this->ops->issueInvoice($delivered);
        $snapshot = DB::table('invoices')->find($invoice)->snapshot;
        $before = (new Reports)->summary('2026-01-01', '2026-12-31');
        $data = $this->data();
        $data['purchase'] = '20000.01';
        $service = new BatchCorrections;
        $plan = $service->preview($this->batch, $data, 'Correct supplier receipt');
        $key = (string) Str::uuid();
        $service->save($this->batch, $data, 'Correct supplier receipt', null, $key, $plan['fingerprint']);
        $service->save($this->batch, $data, 'Correct supplier receipt', null, $key, $plan['fingerprint']);
        $this->assertDatabaseCount('batch_changes', 1);
        $this->assertDatabaseHas('batches', ['purchase' => 2000001, 'physical' => 9334, 'reserved' => 333]);
        $this->assertEquals(66600, $this->ops->allocations($delivered)->sum('cost'));
        $this->assertEquals(66600, $this->ops->allocations($dispatched)->sum('cost'));
        $this->assertEquals(66600, $this->ops->allocations($returned)->sum('cost'));
        $this->assertEquals(0, $this->ops->allocations($pending)->sum('cost'));
        $after = (new Reports)->summary('2026-01-01', '2026-12-31');
        $this->assertEquals($plan['effects']['delivered_profit_change'], $after['profit'] - $before['profit']);
        $this->assertSame($snapshot, DB::table('invoices')->find($invoice)->snapshot);
        $this->assertEquals(2000001, $plan['effects']['remaining_value'] + 133200);
        Livewire::test(Admin::class)->call('viewBatchHistory', $this->batch)->assertSee('Correct supplier receipt')->assertSee('PKR 2,000.00')->assertSee('PKR 20,000.01');
    }

    public function test_quantity_correction_and_count_are_differences_and_invalid_reduction_is_atomic(): void
    {
        $this->order('Delivered');
        $this->order();
        $this->ops->adjust($this->batch, -100, 'Leaking container');
        $data = $this->data();
        $data['original_quantity'] = 11000;
        $service = new BatchCorrections;
        $plan = $service->preview($this->batch, $data, 'Correct received weight', '10500');
        $key = (string) Str::uuid();
        $service->save($this->batch, $data, 'Correct received weight', '10500', $key, $plan['fingerprint']);
        $service->save($this->batch, $data, 'Correct received weight', '10500', $key, $plan['fingerprint']);
        $this->assertDatabaseHas('batches', ['original_quantity' => 11000, 'physical' => 10500, 'reserved' => 333]);
        $this->assertDatabaseHas('stock_adjustments', ['kind' => 'received_correction', 'quantity' => 1000]);
        $this->assertDatabaseHas('stock_adjustments', ['kind' => 'physical', 'quantity' => -67]);
        $this->assertDatabaseCount('batch_changes', 1);
        $data['original_quantity'] = 700;
        try {
            $service->save($this->batch, $data, 'Incorrect receipt weight');
            $this->fail('Impossible reduction accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch.original_quantity', $e->errors());
        }
        $this->assertDatabaseHas('batches', ['original_quantity' => 11000, 'physical' => 10500]);
        $this->assertDatabaseCount('batch_changes', 1);
    }

    public function test_stale_preview_is_rejected_and_date_change_keeps_existing_allocations(): void
    {
        $order = $this->order();
        $allocation = $this->ops->allocations($order)->first()->id;
        $service = new BatchCorrections;
        $data = $this->data();
        $data['received_on'] = '2026-10-01';
        $plan = $service->preview($this->batch, $data, 'Correct receipt date');
        $this->order();
        try {
            $service->save($this->batch, $data, 'Correct receipt date', null, (string) Str::uuid(), $plan['fingerprint']);
            $this->fail('Stale preview accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batchReason', $e->errors());
        }
        $service->save($this->batch, $data, 'Correct receipt date');
        $this->assertSame($allocation, $this->ops->allocations($order)->first()->id);
        $this->assertDatabaseHas('batches', ['received_on' => '2026-10-01']);
    }

    public function test_all_date_defaults_draft_apply_clear_and_report_redirect(): void
    {
        $id = $this->order('Delivered');
        $number = DB::table('orders')->find($id)->number;
        foreach (['dashboard', 'orders', 'stock'] as $page) {
            Livewire::test(Admin::class, ['page' => $page])->assertSet('from', '')->assertSet('to', '');
        }
        Livewire::test(Admin::class, ['page' => 'orders'])->set('from', '2026-11-01')->assertSee($number)->call('filter')->assertDontSee($number)->call('clearDates')->assertSet('from', '')->assertSee($number)->set('to', '2026-10-01')->call('filter')->assertSee($number)->set('from', '2026-10-02')->call('filter')->assertHasErrors('to')->assertSee($number);
        Livewire::test(Admin::class)->assertSee('Business reconciliation')->assertSee('View Breakdown');
        $this->get('/reports?from=2026-10-01&to=2026-10-03')->assertRedirect('/?from=2026-10-01&to=2026-10-03');
    }

    public function test_paisa_reconciles_when_corrected_stock_is_fully_dispatched_or_lost(): void
    {
        DB::table('batches')->where('id', $this->batch)->update(['original_quantity' => 999, 'physical' => 999, 'purchase' => 100]);
        $first = $this->order('Delivered');
        $second = $this->order('Delivered');
        $third = $this->order('Dispatched');
        $data = $this->data();
        $data['purchase'] = '2.00';
        $service = new BatchCorrections;
        $plan = $service->preview($this->batch, $data, 'Correct rounding fixture');
        $service->save($this->batch, $data, 'Correct rounding fixture');
        $this->assertEquals([66, 67, 67], DB::table('allocations')->orderBy('id')->pluck('cost')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals(200, DB::table('allocations')->sum('cost'));
        $this->assertSame(0, $plan['effects']['remaining_value']);
        $this->ops->transition($third, 'Cancelled', '2026-10-03');
        $this->ops->returnStock($third);
        $plan = $service->preview($this->batch, $data, 'Lost returned container', '0');
        $service->save($this->batch, $data, 'Lost returned container', '0');
        $this->assertEquals(67, $plan['effects']['loss_cost']);
        $this->assertEquals(0, $plan['effects']['remaining_value']);
    }

    public function test_quantity_reduction_respects_overlapping_historical_dispatches_after_returns(): void
    {
        $first = $this->order('Dispatched');
        $second = $this->order('Dispatched');
        foreach ([$first, $second] as $id) {
            $this->ops->transition($id, 'Cancelled', '2026-10-03');
            $this->ops->returnStock($id);
        }
        $data = $this->data();
        $data['original_quantity'] = 500;
        try {
            (new BatchCorrections)->save($this->batch,$data,'Correct receipt quantity');
            $this->fail('Historical overlap ignored');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('666',$e->errors()['batch.original_quantity'][0]);
        }
        $this->assertDatabaseHas('batches',['original_quantity' => 10000, 'physical' => 10000]);
    }
}
