<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 40);
            $t->text('address');
            $t->string('normalized_phone', 24)->nullable()->index();
            $t->string('normalized_name')->index();
            $t->string('identity_key', 80)->unique();
            $t->string('review_reason')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('source_order_id')->nullable();
            $t->string('source');
            $t->timestamps();
        });
        Schema::table('orders', fn (Blueprint $t) => $t->foreignId('customer_id')->nullable()->constrained('customers'));
        Schema::create('customer_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->json('before');
            $t->json('after');
            $t->string('source');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_changes');
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customers');
    }
};
