<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom ai_run_id sengaja TIDAK dibuat di sini karena akan menyebabkan foreign key
     * melingkar dengan tabel ai_runs (ai_runs juga mereferensikan messages). Ditambahkan lewat
     * migration terpisah setelah tabel ai_runs ada — lihat
     * 2025_08_04_100195_add_ai_run_id_to_messages_table.php.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete(); // denormalized utk query cepat
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('direction'); // inbound | outbound
            $table->string('sender_type'); // customer | ai | admin | system

            $table->string('whatsapp_message_id')->nullable()->unique();
            $table->string('template_name')->nullable();

            $table->text('body')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_url')->nullable();

            // Salinan status terbaru untuk query cepat; riwayat lengkap ada di message_statuses.
            $table->string('latest_status')->default('queued');

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
