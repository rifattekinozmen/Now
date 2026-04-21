<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_imports', function (Blueprint $table): void {
            $table->string('report_type', 64)->default('endustriyel_hammadde')->after('source');
            $table->text('last_error')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_imports', function (Blueprint $table): void {
            $table->dropColumn(['report_type', 'last_error']);
        });
    }
};
