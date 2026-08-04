<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic & append-only (tidak ada updated_at, log audit tidak pernah diubah).
     * actor_id sengaja tanpa foreign key constraint karena actor_type bisa "system"/"ai"
     * yang tidak punya baris di tabel users.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('actor_type'); // user | system | ai
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('action'); // e.g. lead.status_changed, config.updated, message.sent
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
