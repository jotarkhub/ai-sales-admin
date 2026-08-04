<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MVP: satu baris "business" aktif. Skema sudah siap multi-bisnis
     * (semua tabel inti punya business_id) walau MVP hanya memakai satu.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('assistant_name')->nullable();
            $table->text('assistant_identity')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');

            // Konfigurasi terstruktur (lihat modul "Business Configuration" di spesifikasi).
            $table->json('operating_hours')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('refund_policy')->nullable();
            $table->json('ai_authority_limit')->nullable();
            $table->json('escalation_rules')->nullable();
            $table->json('message_templates')->nullable();
            $table->json('follow_up_schedule')->nullable();
            $table->text('opt_out_instructions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
