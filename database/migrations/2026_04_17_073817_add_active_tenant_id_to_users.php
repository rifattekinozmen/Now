<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('active_tenant_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('tenants')
                ->nullOnDelete();
        });

        // Populate from existing tenant_id (cross-database compatible)
        DB::statement('UPDATE users SET active_tenant_id = tenant_id WHERE tenant_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_tenant_id');
        });
    }
};
