<?php

use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fixture = json_decode(file_get_contents(storage_path('app/qa/controls-fixture.json')), true);
if (! preg_match('/^hives_best_controls_qa_\d+$/', $fixture['database'])) {
    throw new RuntimeException('Expected disposable database');
}
require_once __DIR__.'/qa-database.php';
useBrowserQaDatabase($fixture['database']);
$product = DB::table('products')->first();
for ($i = 8; $i < 22; $i++) {
    $name = $i === 9 ? 'Listing customer with a deliberately long name for responsive layout verification' : 'Listing Customer '.$i;
    if (DB::table('customers')->where('name', $name)->exists()) {
        continue;
    }
    app(Operations::class)->saveOrder(['customer' => $name, 'phone' => $i === 8 ? 'Null' : '0333555'.str_pad($i, 4, '0', STR_PAD_LEFT), 'address' => 'Disposable QA address', 'ordered_on' => '2026-11-01', 'discount' => '0', 'delivery' => '0', 'courier' => '0'], [['product_id' => $product->id, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
}
echo json_encode(['customers' => DB::table('customers')->count(), 'orders' => DB::table('orders')->count()]);
