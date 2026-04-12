<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_tyres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('size')->nullable();
            $table->string('position')->default('front_left');
            $table->date('installed_at')->nullable();
            $table->unsignedInteger('km_installed')->nullable();
            $table->date('removed_at')->nullable();
            $table->unsignedInteger('km_removed')->nullable();
            $table->string('status')->default('active');
            $table->decimal('tread_depth_mm', 5, 1)->nullable();
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tyres');
    }
};
