<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mersis_no')->nullable()->after('tax_id');
            $table->string('kep_address')->nullable()->after('mersis_no');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('payment_term_days');
            $table->string('credit_currency_code', 3)->nullable()->after('credit_limit');
            $table->boolean('is_blacklisted')->default(false)->after('credit_currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['mersis_no', 'kep_address', 'credit_limit', 'credit_currency_code', 'is_blacklisted']);
        });
    }
};
