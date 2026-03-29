<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('public_reference_token', 64)->nullable()->unique()->after('tenant_id');
            $table->json('pod_payload')->nullable()->after('meta');
        });

        $query = DB::table('shipments')->whereNull('public_reference_token');
        foreach ($query->cursor() as $row) {
            $token = self::uniqueToken();
            DB::table('shipments')->where('id', $row->id)->update(['public_reference_token' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['public_reference_token', 'pod_payload']);
        });
    }

    private static function uniqueToken(): string
    {
        do {
            $token = Str::random(48);
        } while (DB::table('shipments')->where('public_reference_token', $token)->exists());

        return $token;
    }
};
