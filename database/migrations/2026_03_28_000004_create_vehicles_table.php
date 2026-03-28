<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plate');
            $table->string('vin')->nullable()->comment('Şasi no');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->date('inspection_valid_until')->nullable()->comment('Muayene geçerlilik');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
