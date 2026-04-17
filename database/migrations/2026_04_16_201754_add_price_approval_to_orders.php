<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_approved_by')->nullable()->after('status');
            $table->timestamp('price_approved_at')->nullable()->after('price_approved_by');

            $table->foreign('price_approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['price_approved_by']);
            $table->dropColumn(['price_approved_by', 'price_approved_at']);
        });
    }
};
