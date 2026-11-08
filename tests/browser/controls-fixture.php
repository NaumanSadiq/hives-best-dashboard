<?php

use App\Models\User;
use App\Services\Operations;
use App\Services\Savings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = dirname(__DIR__, 2).'/storage/app/qa/controls-fixture.json';
$mode = $argv[1] ?? 'prepare';
if ($mode === 'prepare') {
    $database = 'hives_best_controls_qa_'.time();
    DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} else {
    $fixture = json_decode(file_get_contents($path), true);
    $database = $fixture['database'];
}
config(['database.connections.mysql.database' => $database]);
DB::purge('mysql');
if ($mode === 'worker') {
    $payload = json_decode(file_get_contents(dirname(__DIR__, 2).'/storage/app/qa/savings-race.json'), true);
    try {
        app(Savings::class)->save($payload['data'], $argv[2] === 'same' ? $payload['key'] : (string) Str::uuid(), $payload['review']);
        echo json_encode(['result' => 'saved']);
    } catch (ValidationException) {
        echo json_encode(['result' => 'stale']);
    }exit;
}
if ($mode === 'race') {
    $box = new Savings;
    if (! $box->state()['revision']) {
        $data = ['type' => 'opening', 'amount' => '1000', 'date' => today()->toDateString(), 'note' => 'Concurrent test opening'];
        $box->save($data, (string) Str::uuid(), $box->preview($data, $box->state()));
    }
    $data = ['type' => 'add', 'amount' => '10', 'date' => today()->toDateString(), 'note' => 'Concurrent addition'];
    $payload = ['data' => $data, 'key' => (string) Str::uuid(), 'review' => $box->preview($data, $box->state())];
    file_put_contents(dirname(__DIR__, 2).'/storage/app/qa/savings-race.json', json_encode($payload));
    echo json_encode($box->state());
    exit;
}
if ($mode === 'inspect') {
    echo json_encode(['savings' => (new Savings)->state(), 'entries' => DB::table('savings_entries')->count(), 'corrections' => DB::table('order_corrections')->count(), 'receipts' => DB::table('receipts')->count(), 'invoices' => DB::table('invoices')->count()]);
    exit;
}
Artisan::call('migrate', ['--force' => true]);
$credentials = json_decode(file_get_contents(dirname(__DIR__, 2).'/storage/qa-login.json'), true);
$user = new User(['name' => 'Controls QA', 'email' => $credentials['email'], 'password' => $credentials['password']]);
$user->is_admin = true;
$user->save();
auth()->login($user);
$product = DB::table('products')->insertGetId(['name' => 'QA honey', 'kind' => 'honey', 'stock_key' => 'controls', 'pack_quantity' => 500, 'price' => 100000]);
DB::table('batches')->insert(['reference' => 'Controls stock', 'stock_key' => 'controls', 'received_on' => '2026-11-01', 'original_quantity' => 100000, 'physical' => 100000, 'purchase' => 1000000]);
$ops = new Operations;
$ids = [];
for ($i = 0; $i < 28; $i++) {
    $id = $ops->saveOrder(['customer' => $i < 7 ? 'Buyer '.$i : 'Repeat buyer', 'phone' => $i < 7 ? '0333123456'.$i : '03339876543', 'address' => 'QA address', 'ordered_on' => '2026-11-01', 'discount' => '0', 'delivery' => '100', 'courier' => '50'], [['product_id' => $product, 'quantity' => 1, 'price' => '1000']], (string) Str::uuid());
    $ids[] = $id;
    if ($i < 7) {
        $ops->payment($id, 'receipt', $i === 0 ? '500' : '1100', '2026-11-02', 'QA transfer', (string) Str::uuid());
        $ops->transition($id, 'Dispatched', '2026-11-02');
        $ops->transition($id, 'Delivered', '2026-11-03');
    }
    if ($i === 2) {
        $ops->transition($id, 'Cancelled', '2026-11-04');
    }
}
$ops->issueInvoice($ids[0]);
$fixture = ['database' => $database, 'order' => $ids[0], 'number' => DB::table('orders')->find($ids[0])->number];
file_put_contents($path,json_encode($fixture));
echo json_encode($fixture);
