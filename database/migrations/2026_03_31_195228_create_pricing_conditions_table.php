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
        Schema::create('pricing_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contract_no')->nullable();
            $table->string('material_code')->nullable(); // CLN-0100, CEM-0101 vb.
            $table->string('route_from');
            $table->string('route_to');
            $table->decimal('distance_km', 8, 1)->default(0);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('price_per_ton', 10, 4)->default(0);
            $table->decimal('min_tonnage', 8, 2)->default(0);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_conditions');
    }
};
