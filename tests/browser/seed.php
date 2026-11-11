<?php

use App\Models\User;
use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Disposable MariaDB QA data; never seeds the business database.
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('CREATE DATABASE IF NOT EXISTS hives_best_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
require_once __DIR__.'/qa-database.php';
useBrowserQaDatabase('hives_best_qa');
Artisan::call('migrate', ['--force' => true]);
if (DB::table('users')->exists()) {
    echo 'QA already prepared.'.PHP_EOL;
    exit;
}
$password = Str::password(24);
$user = new User(['name' => 'Mia', 'email' => 'qa@hive.test', 'password' => $password]);
$user->is_admin = true;
$user->save();
file_put_contents($root.'/storage/qa-login.json', json_encode(['email' => 'qa@hive.test', 'password' => $password]));
$half = DB::table('products')->insertGetId(['name' => 'Sidr Honey · 500 g', 'stock_key' => 'sidr', 'kind' => 'honey', 'pack_quantity' => 500, 'price' => 120000, 'low_stock' => 2000]);
$full = DB::table('products')->insertGetId(['name' => 'Sidr Honey · 1 kg', 'stock_key' => 'sidr', 'kind' => 'honey', 'pack_quantity' => 1000, 'price' => 220000, 'low_stock' => 2000]);
DB::table('products')->insert(['name' => 'Roasted Almonds · 250 g pack', 'stock_key' => 'almond-packs', 'kind' => 'snack', 'pack_quantity' => 1, 'price' => 65000, 'low_stock' => 10]);
DB::table('batches')->insert(['reference' => 'QA-SIDR-01', 'stock_key' => 'sidr', 'received_on' => today()->startOfMonth()->toDateString(), 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 1000000, 'packaging' => 100000, 'branding' => 50000, 'other' => 50000]);
DB::table('batches')->insert(['reference' => 'QA-ALMOND-01', 'stock_key' => 'almond-packs', 'received_on' => today()->startOfMonth()->toDateString(), 'original_quantity' => 50, 'physical' => 50, 'reserved' => 0, 'purchase' => 2000000, 'packaging' => 100000, 'branding' => 50000, 'other' => 0]);
$ops = new Operations;
foreach (['Ayesha Khan', 'Hassan Ali', 'Sara Ahmed', 'Omar Farooq'] as $n => $name) {
    $day = today()->subDays(3 - $n)->toDateString();
    $id = $ops->saveOrder(['customer' => $name, 'phone' => '03000000000', 'address' => 'Disposable QA delivery address, Karachi', 'ordered_on' => $day, 'discount' => '100', 'delivery' => '200', 'courier' => '150', 'tracking' => 'QA-TRACK', 'notes' => 'Disposable QA order'], [['product_id' => $half, 'quantity' => 1, 'price' => '1200'], ['product_id' => $full, 'quantity' => 1, 'price' => '2200']], (string) Str::uuid());
    $ops->payment($id, 'receipt', '1000', $day, 'QA partial transfer', (string) Str::uuid());
    if ($n < 3) {
        $ops->transition($id, 'Dispatched', $day);
    }
    if ($n < 2) {
        $ops->transition($id, 'Delivered', $day);
        $ops->payment($id, 'receipt', '2500', $day, 'QA settlement', (string) Str::uuid());
    }
    if ($n === 0) {
        $ops->issueInvoice($id);
    }
}
echo 'Disposable QA database ready. Business database untouched. Login stored locally in storage/qa-login.json.'.PHP_EOL;
