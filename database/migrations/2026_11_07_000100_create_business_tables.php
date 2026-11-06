<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('operation_locks', fn (Blueprint $t) => $t->id());
        DB::table('operation_locks')->insert(['id' => 1]);
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('stock_key')->index();
            $t->string('kind');
            $t->unsignedInteger('pack_quantity');
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('low_stock')->default(0);
            $t->timestamps();
        });
        Schema::create('batches', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('stock_key')->index();
            $t->date('received_on');
            $t->unsignedBigInteger('original_quantity');
            $t->bigInteger('physical');
            $t->unsignedBigInteger('reserved')->default(0);
            foreach (['purchase', 'packaging', 'branding', 'other'] as $cost) {
                $t->unsignedBigInteger($cost)->default(0);
            }
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->string('number')->unique();
            $t->string('customer');
            $t->string('phone', 40);
            $t->text('address');
            $t->date('ordered_on')->index();
            $t->string('status')->default('Pending')->index();
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('delivery')->default(0);
            $t->unsignedBigInteger('courier')->default(0);
            $t->unsignedBigInteger('total');
            $t->string('tracking')->nullable();
            $t->text('notes')->nullable();
            $t->date('dispatched_on')->nullable();
            $t->date('delivered_on')->nullable()->index();
            $t->timestamp('returned_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->string('name');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('pack_quantity');
            $t->unsignedBigInteger('unit_price');
        });
        Schema::create('allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained();
            $t->foreignId('batch_id')->constrained('batches');
            $t->unsignedBigInteger('quantity');
            $t->unsignedBigInteger('cost')->default(0);
            $t->unsignedBigInteger('merchandise')->default(0);
            $t->boolean('dispatched')->default(false);
            $t->boolean('returned')->default(false);
        });
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->uuid('request_key')->unique();
            $t->string('kind');
            $t->unsignedBigInteger('amount');
            $t->date('paid_on')->index();
            $t->string('reference')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('batches');
            $t->bigInteger('quantity');
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('number')->unique();
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['order_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['invoices', 'stock_adjustments', 'receipts', 'allocations', 'order_items', 'orders', 'batches', 'products', 'operation_locks'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
