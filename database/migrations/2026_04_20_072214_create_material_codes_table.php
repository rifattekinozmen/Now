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
        Schema::create('material_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique()->comment('e.g. CEM-0101-DOK');
            $table->string('name', 200);
            $table->string('category', 40)->comment('raw_material|cement|packaged|fertilizer|mine|other');
            $table->string('handling_type', 40)->nullable()->comment('bulk|bagged|bigbag|palletized|adr');
            $table->boolean('is_adr')->default(false)->comment('Tehlikeli madde');
            $table->string('unit', 20)->default('ton')->comment('ton|pcs|bag');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_codes');
    }
};
