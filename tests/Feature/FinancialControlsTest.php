<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Customers;
use App\Services\Operations;
use App\Services\OrderCorrections;
use App\Services\Reports;
use App\Services\Savings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialControlsTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $name = 'Buyer', string $phone = '03331234567'): int
    {
        $product = DB::table('products')->insertGetId(['name' => 'Honey', 'kind' => 'honey', 'stock_key' => 'honey', 'pack_quantity' => 500, 'price' => 100000]);
        if (! DB::table('batches')->exists()) {
            DB::table('batches')->insert(['reference' => 'QA', 'stock_key' => 'honey', 'received_on' => today()->toDateString(), 'original_quantity' => 100000, 'physical' => 100000, 'purchase' => 2000000]);
        }

        return app(Operations::class)->saveOrder(['customer' => $name, 'phone' => $phone, 'address' => 'QA', 'ordered_on' => today()->toDateString(), 'discount' => '0', 'delivery' => '100', 'courier' => '50'], [['product_id' => $product, 'quantity' => 2, 'price' => '1000']], (string) Str::uuid());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
    }

    public function test_delivered_correction_preserves_stock_cash_and_invoice_and_updates_reports(): void
    {
        $ops = new Operations;
        $id = $this->order();
        $date = today()->toDateString();
        $ops->payment($id, 'receipt', '1000', $date, 'Bank', (string) Str::uuid());
        $ops->transition($id, 'Dispatched', $date);
        $ops->transition($id, 'Delivered', $date);
        $invoice = $ops->issueInvoice($id);
        $original = DB::table('invoices')->find($invoice)->snapshot;
        $stock = DB::table('batches')->get()->toJson();
        $allocation = DB::table('allocations')->first();
        $service = new OrderCorrections;
        $snapshot = $service->snapshot($id);
        $item = $snapshot['items'][0]['id'];
        $data = ['prices' => [$item => '800'], 'discount' => '100', 'delivery' => '150', 'courier' => '75'];
        $review = $service->preview($id, $data, 'Correct quoted amount', $service->fingerprint($snapshot));
        $key = (string) Str::uuid();
        $service->save($id, $data, 'Correct quoted amount', $key, $review);
        $service->save($id, $data, 'Correct quoted amount', $key, $review);
        $this->assertDatabaseCount('order_corrections', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame($stock, DB::table('batches')->get()->toJson());
        $saved = DB::table('allocations')->first();
        $this->assertEquals($allocation->quantity, $saved->quantity);
        $this->assertEquals($allocation->cost, $saved->cost);
        $this->assertEquals(150000, $saved->merchandise);
        $summary = (new Reports)->summary($date, $date);
        $this->assertSame(165000, $summary['revenue']);
        $this->assertSame(100000, $summary['cash']);
        $this->assertSame(65000, $summary['pending']);
        $this->assertSame(137500, $summary['profit']);
        $this->assertEquals(65000, (new Customers)->listing()->first()->outstanding);
        $this->assertSame($original, DB::table('invoices')->find($invoice)->snapshot);
        $ops->issueInvoice($id, true);
        $this->assertDatabaseCount('invoices', 2);
        $snapshot = $service->snapshot($id);
        $data['prices'][$item] = '200';
        $data['discount'] = '0';
        $data['delivery'] = '0';
        $review = $service->preview($id, $data, 'Correct lower quote', $service->fingerprint($snapshot));
        $service->save($id, $data, 'Correct lower quote', (string) Str::uuid(), $review);
        $this->assertSame(60000, (new Reports)->summary($date, $date)['refunds']);
        $other = $this->order();
        $this->assertEquals(210000, (new Customers)->listing()->first()->outstanding);
        $summary = (new Reports)->summary($date, $date);
        $this->assertSame($summary['pending'], $summary['active_total'] - $summary['active_applied']);
        Livewire::test(Admin::class, ['page' => 'orders'])->assertSee('Overpaid / refund due')->assertSee('Left to pay')->assertSee('PKR 600.00')->assertSee('Paid');
        $ops->payment($id, 'refund', '600', $date, 'Refund excess', (string) Str::uuid());
        $this->assertSame(40000, $ops->paid($id));
    }

    public function test_stale_order_and_changed_preview_cannot_overwrite(): void
    {
        $id = $this->order();
        $service = new OrderCorrections;
        $snapshot = $service->snapshot($id);
        $data = ['prices' => [$snapshot['items'][0]['id'] => '900'], 'discount' => '0', 'delivery' => '0', 'courier' => '0'];
        $review = $service->preview($id, $data, 'Correct quotation', $service->fingerprint($snapshot));
        app(Operations::class)->payment($id, 'receipt', '100', today()->toDateString(), 'New payment', (string) Str::uuid());
        try {
            $service->save($id, $data, 'Correct quotation', (string) Str::uuid(), $review);
            $this->fail('Stale correction accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('order_corrections', 0);
        }
        $snapshot = $service->snapshot($id);
        $review = $service->preview($id, $data, 'Correct quotation', $service->fingerprint($snapshot));
        $data['delivery'] = '200';
        $this->expectException(ValidationException::class);
        $service->save($id, $data, 'Correct quotation', (string) Str::uuid(), $review);
    }

    public function test_savings_history_reversal_idempotence_stale_balance_and_independence(): void
    {
        $box = new Savings;
        $before = (new Reports)->summary('0001-01-01', '9999-12-31');
        $move = function ($type, $amount, $note, $entry = null) use ($box) {
            $data = ['type' => $type, 'amount' => $amount, 'date' => today()->toDateString(), 'note' => $note, 'entry' => $entry];
            $review = $box->preview($data, $box->state());
            $key = (string) Str::uuid();
            $box->save($data, $key, $review);
            $box->save($data, $key, $review);
        };
        $move('opening', '1000', 'Opening count');
        $move('add', '250.25', 'Personal saving');
        $move('purchase', '100.10', 'Packing tape');
        $this->assertSame(115015, $box->state()['balance']);
        $purchase = DB::table('savings_entries')->where('type', 'purchase')->value('id');
        $move('reversal', '0', 'Wrong purchase amount', $purchase);
        $move('purchase', '90.10', 'Correct packing tape');
        $move('count', '1200', 'Extra money from personal savings');
        $this->assertSame(120000, $box->state()['balance']);
        $this->assertDatabaseCount('savings_entries', 6);
        $this->assertSame($before, (new Reports)->summary('0001-01-01', '9999-12-31'));
        $data = ['type' => 'purchase', 'amount' => '10', 'date' => today()->toDateString(), 'note' => 'Small purchase'];
        $review = $box->preview($data, $box->state());
        $move('add', '5', 'Gift cash');
        try {
            $box->save($data, (string) Str::uuid(), $review);
            $this->fail('Stale balance accepted');
        } catch (ValidationException) {
            $this->assertSame(120500, $box->state()['balance']);
        }
        try {
            $move('purchase', '9999', 'Too expensive');
            $this->fail('Overspent');
        } catch (ValidationException) {
            $this->assertSame(120500, $box->state()['balance']);
        }
        $this->expectException(ValidationException::class);
        $move('reversal', '0', 'Repeated reversal', $purchase);
    }

    public function test_livewire_previews_and_top_five_rankings(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $id = $this->order('Buyer '.$i, '0333123456'.$i);
            app(Operations::class)->payment($id, 'receipt', '100', today()->toDateString(), 'Transfer', (string) Str::uuid());
        }
        $rank = (new Customers)->rankings(today()->toDateString(), today()->toDateString());
        $this->assertCount(5, $rank['orders']);
        $this->assertCount(5, $rank['payments']);
        $this->assertEquals(DB::table('customers')->orderBy('id')->limit(5)->pluck('id')->all(), $rank['payments']->pluck('id')->all());
        $this->assertCount(0, (new Customers)->rankings('2000-01-01', '2000-01-02')['payments']);
        Livewire::test(Admin::class)->call('editAmounts', $id)->set('amountEdit.delivery', '50')->set('amountReason', 'Updated delivery charge')->call('previewAmounts')->assertHasNoErrors()->call('saveAmounts')->assertHasNoErrors()->assertSee('Order amounts corrected');
        Livewire::test(Admin::class)->call('openSavings', 'opening')->set('savingsEdit.amount', '100')->set('savingsEdit.note', 'Opening balance')->call('previewSavings')->assertHasNoErrors()->call('saveSavings')->assertHasNoErrors()->assertSee('Opening balance');
    }
}
