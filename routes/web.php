<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Admin;
use App\Services\Operations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('admin')->middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::prefix('admin')->middleware(['auth', 'can:admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', Admin::class)->name('dashboard');
    foreach (['orders', 'stock', 'customers'] as $page) {
        Route::get('/'.$page, Admin::class)->defaults('page', $page)->name($page);
    }
    Route::get('/reports', fn () => redirect()->route('dashboard', request()->only('from', 'to')))->name('reports');
    Route::post('/orders/{id}/invoice', function (int $id) {
        return redirect()->route('invoice', app(Operations::class)->issueInvoice($id));
    })->whereNumber('id')->name('invoice.issue');
    Route::get('/invoices/{id}', function (int $id) {
        $invoice = DB::table('invoices')->find($id);
        abort_unless($invoice, 404);

        return response()->view('invoice', ['invoice' => $invoice, 'snapshot' => json_decode($invoice->snapshot, true)])->header('Cache-Control', 'private, no-store');
    })->whereNumber('id')->name('invoice');
});

// Preserve bookmarked admin URLs without exposing their contents publicly.
Route::get('/login', fn () => redirect()->route('login'));
Route::middleware(['auth', 'can:admin'])->group(function () {
    foreach (['orders', 'stock', 'customers', 'reports'] as $page) {
        Route::get('/'.$page, fn () => redirect()->route($page, request()->query()));
    }
    Route::get('/invoices/{id}', fn (int $id) => redirect()->route('invoice', ['id' => $id] + request()->query()))->whereNumber('id');
});
