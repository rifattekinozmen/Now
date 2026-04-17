<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('locked_at')->nullable()->after('price_approved_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_at', 'locked_by']);
        });
    }
};
