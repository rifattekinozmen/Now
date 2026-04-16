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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('transaction_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('TRY');
            $table->string('transaction_type')->default('credit'); // credit / debit
            $table->string('reference_no')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('matched_payment_id')->nullable();
            $table->unsignedBigInteger('matched_voucher_id')->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            $table->foreign('matched_payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('matched_voucher_id')->references('id')->on('vouchers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
