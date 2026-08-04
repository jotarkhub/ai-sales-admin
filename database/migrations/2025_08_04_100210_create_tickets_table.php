<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
