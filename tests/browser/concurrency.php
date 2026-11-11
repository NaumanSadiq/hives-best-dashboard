<?php

use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Real row-lock integration probe. This script can only connect to the disposable QA database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/qa-database.php';
useBrowserQaDatabase('hives_best_qa');

if (($argv[1] ?? '') === 'prepare') {
    $pool = 'concurrency-'.Str::lower(Str::random(8));
    $product = DB::table('products')->insertGetId(['name' => 'Disposable concurrency product', 'stock_key' => $pool, 'kind' => 'honey', 'pack_quantity' => 1000, 'price' => 100000, 'low_stock' => 0]);
    $batch = DB::table('batches')->insertGetId(['reference' => $pool, 'stock_key' => $pool, 'received_on' => today()->toDateString(), 'original_quantity' => 1000, 'physical' => 1000, 'reserved' => 0, 'purchase' => 50000, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
    echo json_encode(compact('product', 'batch'));
    exit;
}
if (($argv[1] ?? '') === 'order') {
    try {
        $id = app(Operations::class)->saveOrder(['customer' => 'Concurrency QA', 'phone' => '03000000000', 'address' => 'Disposable QA address', 'ordered_on' => today()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => 'Concurrent worker'], [['product_id' => (int) $argv[2], 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
        echo json_encode(['result' => 'reserved', 'id' => $id]);
    } catch (ValidationException $e) {
        echo json_encode(['result' => 'insufficient']);
    }
    exit;
}
if (($argv[1] ?? '') === 'inspect') {
    echo json_encode(DB::table('batches')->find((int) $argv[2]));
}
