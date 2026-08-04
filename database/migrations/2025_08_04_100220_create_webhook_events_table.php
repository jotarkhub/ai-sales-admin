<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fondasi idempotency untuk semua webhook masuk (WhatsApp, dan bisa dipakai ulang untuk
     * sumber lain nanti). unique(source, external_event_id) mencegah pemrosesan ganda saat
     * provider mengirim retry/duplikat.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // whatsapp | google_form
            $table->string('event_type')->nullable();
            $table->string('external_event_id');
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('status')->default('pending'); // pending | processed | failed | duplicate
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
