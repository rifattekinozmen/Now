<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for filter and sort columns used in admin list pages and GlobalSearch.
     */
    public function up(): void
    {
        // shipments.status — status dropdown filter + stats aggregation query
        if (Schema::hasTable('shipments') && ! $this->indexExists('shipments', 'status')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->index('status', 'idx_shipments_status');
            });
        }

        // shipments.dispatched_at / delivered_at — sort columns in shipments list
        if (Schema::hasTable('shipments') && ! $this->indexExists('shipments', 'dispatched_at')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->index('dispatched_at', 'idx_shipments_dispatched_at');
                $table->index('delivered_at', 'idx_shipments_delivered_at');
            });
        }

        // customers.tax_id — customer search: OR tax_id LIKE ?
        if (Schema::hasTable('customers') && ! $this->indexExists('customers', 'tax_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('tax_id', 'idx_customers_tax_id');
            });
        }

        // orders.ordered_at — sort column in orders list
        if (Schema::hasTable('orders') && ! $this->indexExists('orders', 'ordered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('ordered_at', 'idx_orders_ordered_at');
            });
        }

        // warehouses.name — GlobalSearch: OR name LIKE ?
        if (Schema::hasTable('warehouses') && ! $this->indexExists('warehouses', 'name')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->index('name', 'idx_warehouses_name');
            });
        }

        // employees.national_id — GlobalSearch: OR national_id LIKE ?
        if (Schema::hasTable('employees') && ! $this->indexExists('employees', 'national_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('national_id', 'idx_employees_national_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_shipments_status');
            $table->dropIndexIfExists('idx_shipments_dispatched_at');
            $table->dropIndexIfExists('idx_shipments_delivered_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_customers_tax_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_orders_ordered_at');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_warehouses_name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_employees_national_id');
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
