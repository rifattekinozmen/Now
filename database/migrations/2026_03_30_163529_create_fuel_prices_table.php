<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('fuel_type', 20); // diesel | gasoline | lpg
            $table->decimal('price', 10, 4);
            $table->char('currency', 3)->default('TRY');
            $table->date('recorded_at');
            $table->string('source', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_prices');
    }
};
