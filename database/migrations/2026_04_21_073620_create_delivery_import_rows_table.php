<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('delivery_import_id')->constrained('delivery_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->json('row_data');
            $table->timestamps();

            $table->index(['delivery_import_id', 'row_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_import_rows');
    }
};
