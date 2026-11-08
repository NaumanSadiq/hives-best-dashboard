<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Customers;
use App\Services\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

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
        $this->product = DB::table('products')->insertGetId(['name' => 'Customer honey', 'kind' => 'honey', 'stock_key' => 'customer-honey', 'pack_quantity' => 500, 'price' => 100000, 'low_stock' => 0]);
        DB::table('batches')->insert(['reference' => 'Customer fixture', 'stock_key' => 'customer-honey', 'received_on' => '2026-10-01', 'original_quantity' => 100000, 'physical' => 100000, 'purchase' => 1000000]);
    }

    private function data(array $extra = []): array
    {
        return array_merge(['customer' => 'Neadi Bhai', 'phone' => '03225865405', 'address' => 'First address', 'ordered_on' => '2026-11-01', 'discount' => '0', 'delivery' => '0', 'courier' => '0'], $extra);
    }

    private function order(array $data = [], ?string $key = null, ?int $id = null): int
    {
        return $this->ops->saveOrder($this->data($data), [['product_id' => $this->product, 'quantity' => 1, 'price' => '1000']], $key ?? (string) Str::uuid(), $id);
    }

    private function legacy(string $name, string $phone, string $address, string $created): int
    {
        return DB::table('orders')->insertGetId(['number' => 'LEGACY-'.Str::random(8), 'request_key' => (string) Str::uuid(), 'customer' => $name, 'phone' => $phone, 'address' => $address, 'ordered_on' => '2026-10-01', 'subtotal' => 100000, 'total' => 100000, 'status' => 'Cancelled', 'created_at' => $created, 'updated_at' => $created]);
    }

    public function test_backfill_normalizes_phone_uses_latest_snapshot_and_is_repeatable(): void
    {
        $a = $this->legacy('Neadi Bhai', '03225865405', 'Old', '2026-10-01 10:00:00');
        $b = $this->legacy(' neadi   bhai ', '+92 322 5865405', 'Latest', '2026-11-01 10:00:00');
        $this->legacy('Other Person', '00923225865405', 'Other', '2026-11-01 11:00:00');
        $this->legacy('Missing Phone', 'Null', 'A', '2026-11-01 12:00:00');
        $this->legacy('Missing Phone', '', 'B', '2026-11-01 13:00:00');
        $before = DB::table('orders')->get()->map(fn ($o) => collect($o)->except('customer_id')->all())->toJson();
        $result = (new Customers)->backfill();
        $this->assertSame(4, $result['created']);
        $this->assertSame(5, $result['linked']);
        $this->assertCount(4, $result['review']);
        $customer = DB::table('customers')->find(DB::table('orders')->find($a)->customer_id);
        $this->assertSame('Latest', $customer->address);
        $this->assertEquals($b, $customer->source_order_id);
        $this->assertSame($before, DB::table('orders')->get()->map(fn ($o) => collect($o)->except('customer_id')->all())->toJson());
        (new Customers)->update($customer->id, ['name' => 'Neadi Bhai', 'phone' => '03225865405', 'address' => 'Reviewed profile'], true);
        $again = (new Customers)->backfill();
        $this->assertSame(0, $again['created']);
        $this->assertSame(0, $again['linked']);
        $this->assertSame('Reviewed profile', DB::table('customers')->find($customer->id)->address);
    }

    public function test_same_name_without_same_reliable_phone_is_not_merged(): void
    {
        $this->order();
        $this->order(['phone' => '03339545228']);
        $this->order(['phone' => 'Null']);
        $this->order(['phone' => 'Null']);
        $this->assertDatabaseCount('customers', 4);
    }

    public function test_new_order_requests_are_idempotent_and_matching_phone_name_reuses_customer(): void
    {
        $key = (string) Str::uuid();
        $id = $this->order(key: $key);
        $this->assertSame($id, $this->order(key: $key));
        $this->order(['phone' => '+92 322 5865405', 'customer' => 'neadi bhai']);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('orders', 2);
        try {
            $this->order(['customer' => 'Different Person']);
            $this->fail('Conflicting name silently merged');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order.phone', $e->errors());
        }
        $this->order(['customer' => 'Different Person', 'confirm_distinct' => true]);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_profile_edits_do_not_rewrite_orders_or_issued_invoices(): void
    {
        $id = $this->order();
        $c = DB::table('orders')->find($id)->customer_id;
        $invoice = $this->ops->issueInvoice($id);
        $snapshot = DB::table('invoices')->find($invoice)->snapshot;
        Livewire::test(Admin::class, ['page' => 'customers'])->call('editCustomer', $c)->set('profile.address', 'Future address')->call('saveCustomer')->assertHasNoErrors();
        $this->assertSame('First address', DB::table('orders')->find($id)->address);
        $this->assertSame($snapshot, DB::table('invoices')->find($invoice)->snapshot);
        $form = Livewire::test(Admin::class, ['page' => 'orders'])->call('newOrder')->set('customerSearch', 'NEADI')->assertSee('Neadi Bhai')->call('selectCustomer', $c)->assertSet('order.address', 'Future address')->set('order.address', 'Order only address')->set('lines', [['product_id' => $this->product, 'quantity' => 1, 'price' => '1000']])->call('saveOrder')->assertHasNoErrors();
        $this->assertSame('Future address', DB::table('customers')->find($c)->address);
        $form->call('newOrder')->call('selectCustomer', $c)->set('order.address', 'Explicit update')->set('saveCustomerProfile', true)->set('lines', [['product_id' => $this->product, 'quantity' => 1, 'price' => '1000']])->call('saveOrder')->assertHasNoErrors();
        $this->assertSame('Explicit update', DB::table('customers')->find($c)->address);
    }

    public function test_customer_reassignment_requires_confirmation_and_cannot_move_payment_history(): void
    {
        $id = $this->order();
        $other = $this->order(['customer' => 'Other', 'phone' => '03331234567']);
        $otherCustomer = DB::table('orders')->find($other)->customer_id;
        try {
            $this->order(['customer_id' => $otherCustomer], id: $id);
            $this->fail('Balance moved silently');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order.customer_id', $e->errors());
        }
        $this->order(['customer_id' => $otherCustomer, 'confirm_customer_change' => true], id: $id);
        $this->assertEquals($otherCustomer, DB::table('orders')->find($id)->customer_id);
        $this->ops->payment($id, 'receipt', '100', '2026-11-01', 'Paid', (string) Str::uuid());
        $firstCustomer = DB::table('customers')->where('name', 'Neadi Bhai')->value('id');
        try {
            $this->order(['customer_id' => $firstCustomer, 'confirm_customer_change' => true], id: $id);
            $this->fail('Receipt moved');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order.customer_id', $e->errors());
        }
        $this->assertEquals($otherCustomer, DB::table('orders')->find($id)->customer_id);
        $this->assertSame(10000, $this->ops->paid($id));
    }

    public function test_rankings_aggregate_independently_include_cancelled_cash_and_use_correct_dates(): void
    {
        $a = $this->order();
        $c = DB::table('orders')->find($a)->customer_id;
        $b = $this->order();
        $this->ops->payment($a, 'receipt', '400', '2026-11-02', 'A', (string) Str::uuid());
        $this->ops->payment($a, 'receipt', '200', '2026-11-03', 'B', (string) Str::uuid());
        $this->ops->transition($a, 'Cancelled', '2026-11-04');
        $this->ops->payment($a, 'refund', '100', '2026-11-04', 'Refund', (string) Str::uuid());
        $this->ops->transition($b, 'Dispatched', '2026-11-02');
        $this->ops->transition($b, 'Delivered', '2026-11-03');
        foreach (['A', 'B', 'C', 'D'] as $index => $name) {
            $this->order(['customer' => $name, 'phone' => '0333123456'.$index]);
        }
        $service = new Customers;
        $row = $service->listing()->where('c.id', $c)->first();
        $this->assertEquals(2, $row->total_orders);
        $this->assertEquals(1, $row->delivered_orders);
        $this->assertEquals(1, $row->cancelled_orders);
        $this->assertEquals(50000, $row->net_paid);
        $this->assertEquals(100000, $row->outstanding);
        $rank = $service->rankings('2026-11-01', '2026-11-04');
        $this->assertCount(5, $rank['orders']);
        $this->assertEquals($c, $rank['orders']->first()->id);
        $this->assertEquals(1, $rank['orders']->first()->count);
        $this->assertEquals(50000, $rank['payments']->first()->net);
        $narrow = $service->rankings('2026-11-03', '2026-11-03');
        $this->assertCount(0, $narrow['orders']);
        $this->assertEquals(20000, $narrow['payments']->first()->net);
    }

    public function test_tabs_and_twenty_five_order_pagination_keep_filters(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->order();
        }
        Livewire::test(Admin::class, ['page' => 'orders'])->assertViewHas('orders', fn ($p) => $p->perPage() === 25 && $p->total() === 27)->set('search', 'Neadi')->call('setPage', 2)->assertViewHas('orders', fn ($p) => $p->count() === 2)->set('status','Cancelled')->assertSet('paginators.page',1);
        Livewire::withQueryParams(['tab' => 'customers'])->test(Admin::class)->assertSet('dashboardTab','customers')->set('from','2026-11-01')->call('filter')->assertSet('dashboardTab','customers')->call('clearDates')->assertSet('dashboardTab','customers')->call('selectDashboardTab','finance')->assertSet('dashboardTab','finance');
        Livewire::withQueryParams([])->test(Admin::class)->assertSet('dashboardTab','overview');
    }
}
