<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // lead_created, message_sent, status_changed, escalated, dst.
            $table->text('description')->nullable();

            $table->string('actor_type')->default('system'); // system | ai | admin
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
