<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->string('kind')->nullable());
        // Used product categories were locked by the application before snapshots existed.
        foreach (DB::table('products')->select('id', 'kind')->get() as $product) {
            DB::table('order_items')->where('product_id', $product->id)->update(['kind' => $product->kind]);
        }
        Schema::create('payment_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('receipt_id')->constrained('receipts');
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->uuid('request_key')->unique();
            $t->text('reason');
            $t->json('before');
            $t->json('after');
            $t->bigInteger('cash_change');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_changes');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
