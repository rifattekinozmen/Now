<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('sgk_sicil_no')->nullable()->after('national_id');
            $table->string('military_status', 30)->nullable()->after('sgk_sicil_no');
            $table->string('marital_status', 30)->nullable()->after('military_status');
            $table->string('passport_no')->nullable()->after('marital_status');
            $table->date('passport_expiry_date')->nullable()->after('passport_no');
            $table->string('emergency_contact_name')->nullable()->after('passport_expiry_date');
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relation');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'sgk_sicil_no',
                'military_status',
                'marital_status',
                'passport_no',
                'passport_expiry_date',
                'emergency_contact_name',
                'emergency_contact_relation',
                'emergency_contact_phone',
            ]);
        });
    }
};
