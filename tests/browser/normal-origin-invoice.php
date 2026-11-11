<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('select database() db')->db !== 'hives-best') {
    throw new RuntimeException('Expected read-only local application inspection');
}
$state = [];
foreach (['orders', 'receipts', 'invoices', 'batches', 'allocations', 'customers', 'savings_entries'] as $table) {
    $state[$table] = DB::table($table)->orderBy('id')->get();
}
$hash = hash('sha256', json_encode($state));
$metadata = storage_path('app/qa/normal-origin.json');
if (($argv[1] ?? '') === 'verify') {
    $before = json_decode(file_get_contents($metadata), true);
    if (($before['business_hash'] ?? '') !== $hash) {
        throw new RuntimeException('Business data changed during verification; inspect before proceeding.');
    }
    echo json_encode(['business_data_unchanged' => true, 'read_only' => true]);
    exit;
}
$invoice = DB::table('invoices as i')->join('orders as o', 'o.id', '=', 'i.order_id')->orderByDesc('i.id')->select('i.id', 'o.id as order_id', 'o.number', 'o.customer_id')->first();
if (! $invoice) {
    throw new RuntimeException('No existing invoice available for read-only verification');
}
file_put_contents($metadata, json_encode(['url' => config('app.url'), 'invoice' => $invoice->id, 'order' => $invoice->order_id, 'number' => $invoice->number, 'customer' => $invoice->customer_id, 'business_hash' => $hash]));
echo json_encode(['read_only' => true, 'existing_invoice_available' => true]);
