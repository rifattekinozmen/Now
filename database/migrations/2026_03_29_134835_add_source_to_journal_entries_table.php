<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('source_type', 64)->nullable()->after('memo');
            $table->string('source_key', 191)->nullable()->after('source_type');
            $table->unique(['tenant_id', 'source_type', 'source_key'], 'journal_entries_tenant_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_tenant_source_unique');
            $table->dropColumn(['source_type', 'source_key']);
        });
    }
};
