<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->uuid('request_key')->unique();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
        Schema::create('savings_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->uuid('request_key')->unique();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->bigInteger('before_balance');
            $table->bigInteger('after_balance');
            $table->date('business_date');
            $table->text('explanation');
            $table->unsignedBigInteger('reverses_id')->nullable()->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_entries');
        Schema::dropIfExists('order_corrections');
    }
};
