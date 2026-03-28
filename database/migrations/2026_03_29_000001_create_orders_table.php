<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('order_number');
            $table->string('status', 32)->default('draft');
            $table->timestamp('ordered_at')->useCurrent();
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('freight_amount', 15, 2)->nullable();
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('tonnage', 12, 3)->nullable();
            $table->string('incoterms', 12)->nullable();
            $table->text('loading_site')->nullable();
            $table->text('unloading_site')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
