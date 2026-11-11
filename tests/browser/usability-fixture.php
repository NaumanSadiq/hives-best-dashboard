<?php

use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/qa-database.php';
useBrowserQaDatabase('hives_best_qa');
$ops = new Operations;
$data = ['customer' => 'Usability QA', 'phone' => '03000000000', 'address' => 'Disposable QA address', 'ordered_on' => today()->subMonth()->toDateString(), 'discount' => '0', 'delivery' => '0', 'courier' => '0', 'tracking' => '', 'notes' => ''];
if (($argv[1] ?? '') === 'active') {
    $id = $ops->saveOrder($data, [['product_id' => (int) $argv[2], 'quantity' => 3, 'price' => '1000']], (string) Str::uuid());
    echo json_encode(['id' => $id, 'number' => DB::table('orders')->find($id)->number]);
    exit;
}
if (($argv[1] ?? '') === 'inspect') {
    echo json_encode(DB::table('batches')->select('physical', 'reserved')->find((int) $argv[2]));
    exit;
}
$pool = 'qa-usability-'.Str::lower(Str::random(6));
$product = DB::table('products')->insertGetId(['name' => 'Usability Honey 500 g', 'stock_key' => $pool, 'kind' => 'honey', 'pack_quantity' => 500, 'price' => 100000, 'low_stock' => 500]);
$batchRef = strtoupper($pool);
$unusedRef = $batchRef.'-UNUSED';
$batch = DB::table('batches')->insertGetId(['reference' => $batchRef, 'stock_key' => $pool, 'received_on' => today()->subMonths(2)->toDateString(), 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 1000000, 'packaging' => 100000, 'branding' => 50000, 'other' => 50000]);
$unusedPool = $pool.'-unused';
DB::table('products')->insert(['name' => 'Unused fixture honey', 'stock_key' => $unusedPool, 'kind' => 'honey', 'pack_quantity' => 500, 'price' => 100000, 'low_stock' => 500]);
$unusedBatch = DB::table('batches')->insertGetId(['reference' => $unusedRef, 'stock_key' => $unusedPool, 'received_on' => today()->subMonth()->toDateString(), 'original_quantity' => 1000, 'physical' => 1000, 'reserved' => 0, 'purchase' => 100000, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
$order = $ops->saveOrder($data, [['product_id' => $product, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
echo json_encode(['product' => $product, 'batch' => $batch, 'batchRef' => $batchRef, 'unusedBatch' => $unusedBatch, 'unusedRef' => $unusedRef, 'order' => $order, 'number' => DB::table('orders')->find($order)->number, 'today' => today()->toDateString()]);
