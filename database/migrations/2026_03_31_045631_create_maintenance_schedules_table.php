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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('title', 160);
            $table->string('type', 32)->default('periodic'); // periodic | inspection | repair | tire
            $table->string('status', 32)->default('scheduled'); // scheduled | in_progress | done | cancelled
            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();
            $table->integer('km_at_service')->nullable();
            $table->integer('next_km')->nullable();
            $table->decimal('cost', 18, 2)->nullable();
            $table->string('service_provider', 180)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scheduled_date']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
