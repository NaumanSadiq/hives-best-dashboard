<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', fn (Blueprint $t) => $t->timestamp('used_at')->nullable());
        DB::table('batches')->whereIn('id', DB::table('allocations')->select('batch_id')->union(DB::table('stock_adjustments')->select('batch_id')))->orWhereColumn('physical', '!=', 'original_quantity')->orWhere('reserved', '>', 0)->update(['used_at' => now()]);
        Schema::create('batch_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('batches');
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('kind');
            $t->text('reason');
            $t->json('before');
            $t->json('after');
            $t->timestamp('created_at');
        });
        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->string('action');
            $t->date('business_date');
            $t->text('details')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('batch_changes');
        Schema::table('batches', fn (Blueprint $t) => $t->dropColumn('used_at'));
    }
};
