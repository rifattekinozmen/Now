<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbam_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('co2_kg', 10, 3)->comment('kg CO2 equivalent');
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('fuel_consumption_l', 10, 3)->nullable();
            $table->decimal('tonnage', 12, 3)->nullable();
            $table->string('vehicle_type', 50)->default('truck')->comment('truck|van|rail|ship');
            $table->date('report_date');
            $table->string('status', 20)->default('draft')->comment('draft|submitted|accepted');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'report_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbam_reports');
    }
};
