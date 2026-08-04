<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('interested_product_id')->nullable()
                ->constrained('products')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Nullable & unique: satu submission form -> maksimal satu lead lewat kolom ini.
            $table->string('external_submission_id')->nullable()->unique();

            $table->string('name');
            $table->string('phone_number'); // wajib format E.164, dinormalisasi di service layer
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('budget_estimate')->nullable();
            $table->string('purchase_timeline')->nullable();
            $table->text('needs_notes')->nullable();
            $table->boolean('consent_whatsapp')->default(false);

            // Status mengikuti state machine di docs/ARCHITECTURE.md.
            // String (bukan native DB enum) supaya portable MySQL <-> SQLite; validasi nilai
            // dilakukan lewat App\Enums\LeadStatus di level aplikasi.
            $table->string('status')->default('new');

            $table->unsignedInteger('current_score')->default(0);

            $table->timestamp('opted_out_at')->nullable();

            // "won" hanya lewat konfirmasi admin -> wajib ada aktor manusia yang tercatat.
            $table->foreignId('won_confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('won_confirmed_at')->nullable();

            $table->timestamps();

            $table->index('phone_number');
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
