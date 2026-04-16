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
        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('is_return')->default(false)->after('delivered_at');
            $table->string('return_reason')->nullable()->after('is_return');
            $table->string('return_photo_path')->nullable()->after('return_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['is_return', 'return_reason', 'return_photo_path']);
        });
    }
};
