<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', Admin::class)->name('dashboard');
    foreach (['orders', 'stock', 'customers'] as $page) {
        Route::get('/'.$page, Admin::class)->defaults('page', $page)->name($page);
    }
    Route::get('/reports', fn () => redirect()->route('dashboard', request()->only('from', 'to')))->name('reports');
    Route::get('/invoices/{id}', function (int $id) {
        $invoice = DB::table('invoices')->find($id);
        abort_unless($invoice, 404);

        return response()->view('invoice', ['invoice' => $invoice, 'snapshot' => json_decode($invoice->snapshot, true)])->header('Cache-Control', 'private, no-store');
    })->whereNumber('id')->name('invoice');
});
