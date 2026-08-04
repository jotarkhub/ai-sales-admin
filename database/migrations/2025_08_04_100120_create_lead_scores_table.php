<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history: setiap kali skor dihitung ulang, baris baru dibuat (bukan update).
     * leads.current_score menyimpan salinan nilai terbaru untuk query cepat.
     */
    public function up(): void
    {
        Schema::create('lead_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->integer('total_score');
            $table->integer('previous_score')->nullable();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();

            $table->string('computed_by')->default('system'); // system | admin
            $table->foreignId('computed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['lead_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scores');
    }
};
