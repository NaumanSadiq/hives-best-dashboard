<?php

use App\Models\User;
use App\Services\Operations;
use App\Services\Reports;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = dirname(__DIR__, 2).'/storage/app/qa/financial-fixture.json';
if (($argv[1] ?? '') === 'inspect') {
    $fixture = json_decode(file_get_contents($path), true);
    require_once __DIR__.'/qa-database.php';
    useBrowserQaDatabase($fixture['database']);
    echo json_encode(['summary' => (new Reports)->summary('0001-01-01', '9999-12-31'), 'receipts' => DB::table('receipts')->orderBy('id')->get(), 'changes' => DB::table('payment_changes')->get(), 'orders' => DB::table('orders')->select('id', 'number', 'status', 'total')->get(), 'packs' => (new Reports)->packs('0001-01-01', '9999-12-31')]);
    exit;
}
$database = 'hives_best_financial_qa_'.time();
DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
require_once __DIR__.'/qa-database.php';
useBrowserQaDatabase($database);
Artisan::call('migrate', ['--force' => true]);
$credentials = json_decode(file_get_contents(dirname(__DIR__, 2).'/storage/qa-login.json'), true);
$admin = new User(['name' => 'Financial QA', 'email' => $credentials['email'], 'password' => $credentials['password']]);
$admin->is_admin = true;
$admin->save();
$full = DB::table('products')->insertGetId(['name' => 'QA honey 1 kg', 'kind' => 'honey', 'stock_key' => 'financial-honey', 'pack_quantity' => 1000, 'price' => 400000, 'low_stock' => 1000]);
$half = DB::table('products')->insertGetId(['name' => 'QA honey 500 g', 'kind' => 'honey', 'stock_key' => 'financial-honey', 'pack_quantity' => 500, 'price' => 200000, 'low_stock' => 1000]);
DB::table('batches')->insert(['reference' => 'Financial honey fixture', 'stock_key' => 'financial-honey', 'received_on' => '2026-11-01', 'original_quantity' => 100000, 'physical' => 100000, 'purchase' => 10000000, 'packaging' => 1000000, 'branding' => 1000000, 'other' => 0]);
$ops = new Operations;
$base = ['customer' => 'Fixture baseline', 'phone' => '0', 'address' => 'QA', 'ordered_on' => '2026-11-07', 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => ''];
$baseline = $ops->saveOrder($base, [['product_id' => $full, 'quantity' => 1, 'price' => '29800']], (string) Str::uuid());
$ops->transition($baseline, 'Dispatched', '2026-11-07');
$ops->transition($baseline, 'Delivered', '2026-11-07');
$ops->payment($baseline, 'receipt', '29800', '2026-11-07', 'Baseline payments fixture', (string) Str::uuid());
$old = $ops->saveOrder(array_merge($base, ['customer' => 'Replacement workflow QA', 'phone' => '111', 'delivery' => '250']), [['product_id' => $full, 'quantity' => 2, 'price' => '4000']], (string) Str::uuid());
$data = ['database' => $database, 'old' => $old, 'oldNumber' => DB::table('orders')->find($old)->number, 'full' => $full, 'half' => $half, 'today' => today()->toDateString()];
file_put_contents($path, json_encode($data));
echo json_encode($data);
