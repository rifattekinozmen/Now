<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for frequently searched/filtered columns.
     * These improve GlobalSearch and filter query performance.
     */
    public function up(): void
    {
        // Vehicle search by plate
        if (Schema::hasTable('vehicles') && ! $this->indexExists('vehicles', 'plate')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->index('plate', 'idx_vehicles_plate');
            });
        }

        // Customer search by name
        if (Schema::hasTable('customers') && ! $this->indexExists('customers', 'legal_name')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('legal_name', 'idx_customers_legal_name');
                $table->index('trade_name', 'idx_customers_trade_name');
            });
        }

        // Order search by order_number
        if (Schema::hasTable('orders') && ! $this->indexExists('orders', 'order_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('order_number', 'idx_orders_order_number');
            });
        }

        // Shipment search by token
        if (Schema::hasTable('shipments') && ! $this->indexExists('shipments', 'public_reference_token')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->index('public_reference_token', 'idx_shipments_token');
            });
        }

        // Warehouse search by code
        if (Schema::hasTable('warehouses') && ! $this->indexExists('warehouses', 'code')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->index('code', 'idx_warehouses_code');
            });
        }

        // Employee search by name
        if (Schema::hasTable('employees') && ! $this->indexExists('employees', 'first_name')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('first_name', 'idx_employees_first_name');
                $table->index('last_name', 'idx_employees_last_name');
            });
        }

        // AppNotification unread count queries
        if (Schema::hasTable('app_notifications') && ! $this->indexExists('app_notifications', 'user_id')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->index(['user_id', 'is_read'], 'idx_notifications_user_unread');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_vehicles_plate');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_customers_legal_name');
            $table->dropIndexIfExists('idx_customers_trade_name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_orders_order_number');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_shipments_token');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_warehouses_code');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_employees_first_name');
            $table->dropIndexIfExists('idx_employees_last_name');
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_notifications_user_unread');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $column): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if (in_array($column, $index['columns'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }
};
