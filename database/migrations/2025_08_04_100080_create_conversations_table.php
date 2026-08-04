<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('channel')->default('whatsapp');

            // ai_active | human_takeover | closed — lihat state machine conversation.
            $table->string('status')->default('ai_active');

            $table->text('summary')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
