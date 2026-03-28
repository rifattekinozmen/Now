<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('partner_number')->nullable()->comment('SAP BP / iş ortağı no');
            $table->string('tax_id')->nullable();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'legal_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
