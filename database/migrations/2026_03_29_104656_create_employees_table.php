<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_id', 11)->nullable()->comment('T.C. kimlik no');
            $table->string('blood_group', 8)->nullable()->comment('Kan grubu');
            $table->boolean('is_driver')->default(false);
            $table->string('license_class', 16)->nullable()->comment('Ehliyet sınıfı');
            $table->date('license_valid_until')->nullable();
            $table->date('src_valid_until')->nullable()->comment('SRC belge bitiş');
            $table->date('psychotechnical_valid_until')->nullable()->comment('Psikoteknik bitiş');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'national_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
