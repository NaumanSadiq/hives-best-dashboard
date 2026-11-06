<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Operations;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private int $productId;

    private int $batchId;

    private Operations $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $this->ops = new Operations;
        $this->productId = DB::table('products')->insertGetId(['name' => 'QA Honey 500 g', 'stock_key' => 'qa-honey', 'kind' => 'honey', 'pack_quantity' => 500, 'price' => 100000, 'low_stock' => 1000]);
        $this->batchId = DB::table('batches')->insertGetId(['reference' => 'QA-10000', 'stock_key' => 'qa-honey', 'received_on' => today()->subMonths(2)->toDateString(), 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 1000000, 'packaging' => 100000, 'branding' => 50000, 'other' => 50000]);
    }

    private function data(): array
    {
        return ['customer' => 'Disposable customer', 'phone' => '03000000000', 'address' => 'QA address', 'ordered_on' => today()->subMonth()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => ''];
    }

    private function order(int $packs = 1, ?int $id = null): int
    {
        return $this->ops->saveOrder($this->data(), [['product_id' => $this->productId, 'quantity' => $packs, 'price' => '1000']], (string) Str::uuid(), $id);
    }

    private function batchData(): array
    {
        $data = (array) DB::table('batches')->find($this->batchId);
        foreach (['purchase', 'packaging', 'branding', 'other'] as $field) {
            $data[$field] = Money::input($data[$field]);
        }

return $data;
    }

    public function test_edit_then_cancel_releases_current_reservation_once_and_remains_discoverable(): void
    {
        $id = $this->order();
        $this->order(2, $id);
        $this->assertDatabaseHas('batches', ['id' => $this->batchId, 'reserved' => 1000, 'physical' => 10000]);
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->assertDatabaseHas('batches', ['id' => $this->batchId, 'reserved' => 0, 'physical' => 10000]);
        $this->assertEquals(10000, DB::table('batches')->sum(DB::raw('physical-reserved')));
        $this->assertSame(1, DB::table('order_events')->where('order_id', $id)->where('action', 'Cancelled')->count());
        $number = DB::table('orders')->find($id)->number;
        Livewire::test(Admin::class, ['page' => 'orders'])->assertSet('from', '')->assertSee($number)->set('status', 'Cancelled')->set('search', $number)->assertSee($number)->call('viewOrder', $id)->assertSee('Order & cancellation history', false)->assertSee('Current pending reservations released once.');
        Livewire::test(Admin::class, ['page' => 'orders'])->set('from', today()->startOfMonth()->toDateString())->assertDontSee($number)->call('resetFilters')->assertSee($number);
        Livewire::test(Admin::class, ['page' => 'stock'])->assertSee('10,000 g')->assertDontSee('Apply date range');
    }

    public function test_cancellation_preserves_another_active_orders_reservation(): void
    {
        $active = $this->order(3);
        $cancelled = $this->order();
        $this->order(2, $cancelled);
        $this->ops->transition($cancelled, 'Cancelled', today()->toDateString());
        $this->ops->transition($cancelled, 'Cancelled', today()->toDateString());
        $this->assertDatabaseHas('batches', ['reserved' => 1500, 'physical' => 10000]);
        $this->assertEquals(1500, $this->ops->allocations($active)->sum('quantity'));
        Livewire::test(Admin::class, ['page' => 'stock'])->assertSee('8,500 g')->call('inspectReservations', $this->batchId)->assertSee(DB::table('orders')->find($active)->number);
    }

    public function test_unused_batch_allows_quantity_and_cost_corrections_with_audit(): void
    {
        $data = $this->batchData();
        $data['reference'] = 'QA-CORRECTED';
        $data['original_quantity'] = 12000;
        $data['purchase'] = '11000.25';
        $this->ops->editBatch($this->batchId, $data, 'Correct supplier entry');
        $this->assertDatabaseHas('batches', ['id' => $this->batchId, 'reference' => 'QA-CORRECTED', 'physical' => 12000, 'original_quantity' => 12000, 'purchase' => 1100025]);
        $this->assertDatabaseHas('batch_changes', ['batch_id' => $this->batchId, 'reason' => 'Correct supplier entry']);
        Livewire::test(Admin::class, ['page' => 'stock'])->call('batchForm', $this->batchId)->assertSet('batchLocked', false)->assertSet('batch.purchase', '11000.25');
    }

    public function test_used_batch_preserves_basis_and_snapshots_even_after_cancellation(): void
    {
        $id = $this->order(2);
        $this->ops->transition($id, 'Dispatched', today()->toDateString());
        $cost = DB::table('allocations')->sum('cost');
        $data = $this->batchData();
        $data['reference'] = 'RENAMED';
        $this->ops->editBatch($this->batchId, $data, 'Clarify batch reference');
        $this->assertEquals($cost, DB::table('allocations')->sum('cost'));
        $this->assertTrue($this->ops->batchUsed($this->batchId));
        $this->ops->transition($id, 'Cancelled', today()->toDateString());
        $this->ops->returnStock($id);
        $data['purchase'] = '5';
        try {
            $this->ops->editBatch($this->batchId, $data, 'Attempt basis rewrite');
            $this->fail('Used basis changed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch.purchase', $e->errors());
        }
        $this->assertDatabaseHas('batches', ['purchase' => 1000000, 'original_quantity' => 10000, 'physical' => 10000]);
        Livewire::test(Admin::class, ['page' => 'stock'])->call('batchForm', $this->batchId)->assertSet('batchLocked', true);
    }

    public function test_explicit_adjustment_direction_requires_positive_quantity(): void
    {
        $component = Livewire::test(Admin::class, ['page' => 'stock'])->set('adjustBatch', $this->batchId)->set('adjustDirection', 'remove')->set('adjustQuantity', '500')->set('adjustReason', 'Container loss')->call('adjust')->assertHasNoErrors();
        $this->assertDatabaseHas('batches', ['physical' => 9500]);
        $component->set('adjustDirection', 'restore')->set('adjustQuantity', '500')->set('adjustReason', 'Found sealed container')->call('adjust')->assertHasNoErrors();
        $this->assertDatabaseHas('batches',['physical' => 10000]);
        $component->set('adjustQuantity','-500')->call('adjust')->assertHasErrors(['adjustQuantity'])->assertSet('adjustQuantity','-500');
        $this->assertSame(2,DB::table('stock_adjustments')->count());
    }
}
