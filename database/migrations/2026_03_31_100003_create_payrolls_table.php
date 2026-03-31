<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_salary', 18, 2);
            $table->json('deductions')->nullable(); // SGK, vergi, avans kesintisi vb.
            $table->decimal('net_salary', 18, 2);
            $table->string('currency_code', 3)->default('TRY');
            $table->string('status', 32)->default('draft'); // draft | approved | paid
            $table->string('pdf_path')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'period_start']);
            $table->unique(['tenant_id', 'employee_id', 'period_start'], 'payrolls_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
