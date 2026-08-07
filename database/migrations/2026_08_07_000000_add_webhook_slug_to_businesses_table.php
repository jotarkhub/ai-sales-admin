<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fase 8c — webhook per bisnis. URL webhook Meta jadi /api/v1/whatsapp/webhook/{webhook_slug}
 * (bukan {id} auto-increment yang gampang ditebak/diurut). Nullable di skema karena kolom baru
 * di tabel yang mungkin sudah berisi baris, tapi langsung dibackfill di migration ini supaya
 * tidak ada baris businesses lama yang tertinggal tanpa slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('webhook_slug')->nullable()->unique()->after('id');
        });

        foreach (DB::table('businesses')->whereNull('webhook_slug')->pluck('id') as $id) {
            DB::table('businesses')->where('id', $id)->update(['webhook_slug' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('webhook_slug');
        });
    }
};
