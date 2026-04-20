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
        Schema::create('tax_offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Vergi dairesi kodu');
            $table->string('name', 150);
            $table->string('city', 100)->comment('İl adı');
            $table->string('district', 100)->nullable()->comment('İlçe');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('city');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_offices');
    }
};
