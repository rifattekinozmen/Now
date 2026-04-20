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
            $table->string('cargo_type', 32)->nullable()->after('incoterms')
                ->comment('bulk|bagged|bigbag|palletized|other');
            $table->unsignedSmallInteger('pallet_count')->nullable()->after('cargo_type');
            $table->string('pallet_standard', 50)->nullable()->after('pallet_count')
                ->comment('euro_80x120|industrial_100x120|other');
            $table->string('adr_class', 20)->nullable()->after('pallet_standard')
                ->comment('ADR dangerous goods class e.g. 3,8,9');
            $table->boolean('temperature_control')->default(false)->after('adr_class');
            $table->string('temperature_range', 30)->nullable()->after('temperature_control')
                ->comment('e.g. +2/+8, -18');
            $table->decimal('insurance_value', 15, 2)->nullable()->after('temperature_range');
            $table->string('insurance_currency_code', 3)->nullable()->after('insurance_value');
            // FK references to customer_addresses for reactive address selection
            $table->unsignedBigInteger('loading_address_id')->nullable()->after('loading_site');
            $table->unsignedBigInteger('delivery_address_id')->nullable()->after('unloading_site');

            $table->foreign('loading_address_id')
                ->references('id')->on('customer_addresses')
                ->onDelete('set null');
            $table->foreign('delivery_address_id')
                ->references('id')->on('customer_addresses')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['loading_address_id']);
            $table->dropForeign(['delivery_address_id']);
            $table->dropColumn([
                'cargo_type',
                'pallet_count',
                'pallet_standard',
                'adr_class',
                'temperature_control',
                'temperature_range',
                'insurance_value',
                'insurance_currency_code',
                'loading_address_id',
                'delivery_address_id',
            ]);
        });
    }
};
