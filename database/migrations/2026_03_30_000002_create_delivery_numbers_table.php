<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('pin_code');
            $table->string('sas_no')->nullable()->comment('SAP SAS ile eşleşme');
            $table->string('status', 32)->default('available');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pin_code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_numbers');
    }
};
