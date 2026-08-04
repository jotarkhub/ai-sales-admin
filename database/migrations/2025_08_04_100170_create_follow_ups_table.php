<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_message_id')->nullable()->constrained('messages')->nullOnDelete();

            // form_no_response, first_message_no_reply, conversation_stalled, offer_sent,
            // admin_scheduled, customer_requested_date, dst.
            $table->string('trigger_type');

            $table->timestamp('scheduled_at');
            $table->string('status')->default('pending'); // pending | sent | cancelled | skipped
            $table->string('channel')->default('whatsapp');
            $table->string('template_used')->nullable();

            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedInteger('max_attempts')->default(3);

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
