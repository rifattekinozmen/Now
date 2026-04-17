<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tenants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });

        // Populate from existing tenant_id on users (CURRENT_TIMESTAMP works in both MySQL and SQLite)
        DB::statement('
            INSERT INTO user_tenants (user_id, tenant_id, created_at, updated_at)
            SELECT id, tenant_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM users
            WHERE tenant_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tenants');
    }
};
