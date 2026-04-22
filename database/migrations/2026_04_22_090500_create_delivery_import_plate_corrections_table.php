<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_import_plate_corrections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('delivery_import_id')->constrained('delivery_imports')->cascadeOnDelete();
            $table->foreignId('delivery_import_row_id')->constrained('delivery_import_rows')->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->string('old_plate', 64);
            $table->string('new_plate', 64);
            $table->string('status', 20)->default('pending')->index(); // pending|approved|rejected
            $table->text('request_reason')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_import_plate_corrections');
    }
};
