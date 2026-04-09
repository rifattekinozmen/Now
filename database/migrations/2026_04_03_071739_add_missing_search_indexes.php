<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders.sas_no — filtered in order search queries
        if (! $this->indexExists('orders', 'sas_no')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('sas_no', 'idx_orders_sas_no');
            });
        }

        // customers.trade_name — skipped by previous migration due to legal_name already indexed
        if (! $this->indexExists('customers', 'trade_name')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('trade_name', 'idx_customers_trade_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_orders_sas_no');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_customers_trade_name');
        });
    }

    private function indexExists(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (in_array($column, $index['columns'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
};
