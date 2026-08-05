<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Definisi field tambahan yang bisa dikustomisasi per bisnis lewat form builder
     * (App\Http\Controllers\LeadFieldDefinitionController). Contoh pemakaian: klien
     * konsultan LPK menambah field "No KTP Pemohon", "Nama Bapak", dst. yang tidak relevan
     * untuk bisnis lain — karena itu field-field ini TIDAK di-hardcode ke tabel leads.
     */
    public function up(): void
    {
        Schema::create('lead_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('key'); // slug stabil, dipakai Apps Script CUSTOM_FIELD_MAP & payload API
            $table->string('label');
            $table->string('field_type')->default('text'); // text/textarea/number/date/select/phone/email/nik
            $table->boolean('is_required')->default(false);

            // Field sensitif (mis. NIK/KTP) disimpan terenkripsi (lead_field_values.value_encrypted)
            // dan wajib di-redact di audit log / lead activity — lihat LeadIntakeService.
            $table->boolean('is_sensitive')->default(false);

            $table->json('options')->nullable(); // untuk field_type=select: ["Opsi A","Opsi B"]
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['business_id', 'key']);
            $table->index(['business_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_field_definitions');
    }
};
