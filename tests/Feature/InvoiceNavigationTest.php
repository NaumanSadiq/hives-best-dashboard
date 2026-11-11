<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\User;
use App\Services\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_and_view_uses_authenticated_new_tab_and_preserves_dashboard_state(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        $product = DB::table('products')->insertGetId(['name' => 'QA honey', 'kind' => 'honey', 'stock_key' => 'navigation', 'pack_quantity' => 500, 'price' => 100000]);
        DB::table('batches')->insert(['reference' => 'Navigation QA', 'stock_key' => 'navigation', 'received_on' => today()->toDateString(), 'original_quantity' => 1000, 'physical' => 1000, 'purchase' => 10000]);
        $id = app(Operations::class)->saveOrder(['customer' => 'Navigation QA', 'phone' => '03331234567', 'address' => 'QA', 'ordered_on' => today()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0'], [['product_id' => $product, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
        $component = Livewire::test(Admin::class)->call('selectDashboardTab', 'charts')->call('viewOrder', $id);
        $component->assertSee(route('invoice.issue', $id), false)->assertSeeHtml('target="_blank" rel="noopener"');
        $this->post(route('invoice.issue', $id))->assertRedirect(route('invoice', DB::table('invoices')->value('id')));
        $this->post(route('invoice.issue', $id))->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
        $component->call('viewOrder', $id)->assertSee('View Invoice')->assertSeeHtml('target="_blank" rel="noopener"')->call('invoice', true)->assertSet('dashboardTab', 'charts')->assertSet('selected', $id)->assertNoRedirect();
        $this->assertDatabaseCount('invoices', 2);
    }
}
