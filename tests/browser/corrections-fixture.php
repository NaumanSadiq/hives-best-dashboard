<?php

use App\Services\Operations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.mysql.database' => 'hives_best_qa']);
DB::purge('mysql');
if (($argv[1] ?? '') === 'inspect') {
    $id = (int) $argv[2];
    echo json_encode(['batch' => DB::table('batches')->find($id), 'changes' => DB::table('batch_changes')->where('batch_id', $id)->get(), 'allocations' => DB::table('allocations')->where('batch_id', $id)->get(), 'invoice' => DB::table('invoices')->find((int) $argv[3])->snapshot]);
    exit;
}
$ops = new Operations;
$pool = 'qa-correct-'.Str::lower(Str::random(6));
$product = DB::table('products')->insertGetId(['name' => 'Correction QA honey', 'kind' => 'honey', 'stock_key' => $pool, 'pack_quantity' => 333, 'price' => 100000, 'low_stock' => 500]);
$reference = strtoupper($pool);
$batch = DB::table('batches')->insertGetId(['reference' => $reference, 'stock_key' => $pool, 'received_on' => today()->toDateString(), 'original_quantity' => 10000, 'physical' => 10000, 'reserved' => 0, 'purchase' => 200000, 'packaging' => 0, 'branding' => 0, 'other' => 0]);
$ids = [];
foreach (['Delivered', 'Dispatched', 'Returned', 'Pending'] as $status) {
    $id = $ops->saveOrder(['customer' => 'Correction browser QA', 'phone' => '0', 'address' => 'Disposable QA', 'ordered_on' => '2026-10-01', 'discount' => '0', 'delivery' => '0', 'courier' => '0'], [['product_id' => $product, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
    if ($status !== 'Pending') {
        $ops->transition($id, 'Dispatched', '2026-10-02');
    }
    if ($status === 'Delivered') {
        $ops->transition($id, 'Delivered', '2026-10-03');
    }
    if ($status === 'Returned') {
        $ops->transition($id, 'Cancelled', '2026-10-03');
        $ops->returnStock($id);
    }
    $ids[] = $id;
}
$invoice = $ops->issueInvoice($ids[0]);
echo json_encode(['batch' => $batch, 'reference' => $reference, 'invoice' => $invoice, 'number' => DB::table('orders')->find($ids[3])->number, 'today' => today()->toDateString()]);
