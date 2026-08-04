<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // sent | delivered | read | failed
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['message_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_statuses');
    }
};
