<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mencatat setiap kali model AI dipanggil: model, versi prompt, token, biaya, dan apakah
     * output terstruktur valid. Model TIDAK PERNAH mengubah database langsung — baris di sini
     * adalah rekomendasi yang divalidasi & dieksekusi oleh aplikasi (lihat Conversation Engine
     * di docs/ARCHITECTURE.md).
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('prompt_version_id')->nullable()
                ->constrained('prompt_versions')->nullOnDelete();

            $table->string('provider'); // openai | fake (fake hanya boleh muncul di testing)
            $table->string('model_used');

            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->json('raw_output')->nullable();
            $table->boolean('structured_output_valid')->default(false);
            $table->string('status')->default('success'); // success | failed | guardrail_blocked

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
