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
for ($i = 22; $i < 35; $i++) {
    app(Operations::class)->saveOrder(['customer' => 'Branding Customer '.$i, 'phone' => '0333666'.str_pad($i, 4, '0', STR_PAD_LEFT), 'address' => 'Disposable QA', 'ordered_on' => '2026-11-01', 'discount' => '0', 'delivery' => '0', 'courier' => '0'], [['product_id' => $product->id, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
}
DB::table('products')->where('id', $product->id)->update(['name' => 'Wildflower Honey with a deliberately long product name for responsive table alignment']);
echo json_encode(['database' => DB::selectOne('select database() db')->db, 'customers' => DB::table('customers')->count(), 'orders' => DB::table('orders')->count()]);
