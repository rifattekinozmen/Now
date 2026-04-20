<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('fine_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('TRY');
            $table->string('fine_type', 30)->default('other');
            $table->string('fine_no')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_fines');
    }
};
