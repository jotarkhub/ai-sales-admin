<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable: submission bisa gagal diproses (mis. secret invalid, sengaja disimpan
            // untuk audit) sebelum sempat membuat lead.
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            $table->string('external_submission_id')->unique();
            $table->timestamp('submitted_at');

            // Seluruh jawaban mentah formulir apa adanya, tidak diubah.
            $table->json('raw_payload');

            $table->string('source')->default('google_form');
            $table->boolean('consent_whatsapp')->default(false);

            $table->string('processing_status')->default('pending'); // pending/processed/duplicate/rejected
            $table->string('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_form_submissions');
    }
};
