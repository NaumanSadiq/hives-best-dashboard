<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_changes', function (Blueprint $t) {
            $t->uuid('request_key')->nullable()->unique();
            $t->json('effects')->nullable();
        });
        Schema::table('stock_adjustments', function (Blueprint $t) {
            $t->foreignId('batch_change_id')->nullable()->constrained('batch_changes');
            $t->string('kind')->default('physical');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('batch_change_id');
            $t->dropColumn('kind');
        });
        Schema::table('batch_changes', function (Blueprint $t) {
            $t->dropUnique(['request_key']);
            $t->dropColumn(['request_key', 'effects']);
        });
    }
};
