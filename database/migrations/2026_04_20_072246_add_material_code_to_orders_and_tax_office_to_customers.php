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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('material_code_id')->nullable()->after('cargo_type');
            $table->foreign('material_code_id')
                ->references('id')->on('material_codes')
                ->onDelete('set null');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_office_id')->nullable()->after('tax_id');
            $table->foreign('tax_office_id')
                ->references('id')->on('tax_offices')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['material_code_id']);
            $table->dropColumn('material_code_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['tax_office_id']);
            $table->dropColumn('tax_office_id');
        });
    }
};
