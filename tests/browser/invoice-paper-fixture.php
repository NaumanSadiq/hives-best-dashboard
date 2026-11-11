<?php

use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/qa-database.php';
$fixture = json_decode(file_get_contents(storage_path('app/qa/controls-fixture.json')), true);
useBrowserQaDatabase($fixture['database']);
$product = DB::table('products')->first();
$items = [];
for ($i = 0; $i < 25; $i++) {
    $id = DB::table('products')->insertGetId(['name' => 'Invoice row '.($i + 1).' · naturally delicious honey with a long descriptive pack name', 'kind' => 'honey', 'stock_key' => $product->stock_key, 'pack_quantity' => 500, 'price' => 100000]);
    $items[] = ['product_id' => $id, 'quantity' => 1, 'price' => '1000'];
}
$order = app(Operations::class)->saveOrder(['customer' => 'Invoice paper QA', 'phone' => '03331234567', 'address' => 'Disposable invoice check', 'ordered_on' => '2026-11-01', 'discount' => '0', 'delivery' => '100', 'courier' => '50'], $items, (string) Str::uuid());
$invoice = app(Operations::class)->issueInvoice($order);
file_put_contents(storage_path('app/qa/invoice-paper.json'), json_encode(['database' => DB::selectOne('select database() db')->db, 'short' => 1, 'long' => $invoice]));
echo json_encode(['isolated_database' => $fixture['database'], 'long_invoice' => $invoice]);
