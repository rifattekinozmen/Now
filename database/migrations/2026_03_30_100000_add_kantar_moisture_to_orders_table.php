<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 14, 3)->nullable()->after('tonnage');
            $table->decimal('tara_weight_kg', 14, 3)->nullable()->after('gross_weight_kg');
            $table->decimal('net_weight_kg', 14, 3)->nullable()->after('tara_weight_kg');
            $table->decimal('moisture_percent', 8, 4)->nullable()->after('net_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gross_weight_kg', 'tara_weight_kg', 'net_weight_kg', 'moisture_percent']);
        });
    }
};
