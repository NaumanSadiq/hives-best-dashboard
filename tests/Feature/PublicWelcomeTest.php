<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_entry_does_not_query_or_expose_business_data(): void
    {
        DB::enableQueryLog();
        $this->get('/')->assertOk()->assertSee('welcome-root')->assertDontSee('wire:snapshot', false);
        $businessQueries = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/\b(orders|products|receipts|customers|invoices|batches)\b/i', $q['query']));
        $this->assertCount(0, $businessQueries);
        $this->assertSame('/admin/dashboard', route('dashboard', absolute: false));
        $this->assertSame('/admin/orders', route('orders', absolute: false));
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/invoices/1')->assertRedirect(route('login'));
    }

    public function test_authentication_intended_redirect_and_legacy_links_remain_inside_admin(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => bcrypt('Welcome-check-123')]);
        $this->get('/admin/orders?from=2026-11-01')->assertRedirect(route('login'));
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Welcome-check-123'])->assertRedirect('/admin/orders?from=2026-11-01');
        $this->get('/admin/login')->assertRedirect(route('dashboard'));
        $this->get('/')->assertOk();
        $this->get('/orders?from=2026-11-01')->assertRedirect('/admin/orders?from=2026-11-01');
        $this->get('/invoices/1')->assertRedirect('/admin/invoices/1');
        $signed = URL::temporarySignedRoute('invoice', now()->addMinutes(5), ['id' => 1]);
        $this->assertStringContainsString('/admin/invoices/1?', $signed);
        $this->assertTrue(URL::hasValidSignature(Request::create($signed)));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}
