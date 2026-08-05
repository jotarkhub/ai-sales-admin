<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tepat satu dari (value, value_encrypted) yang terisi per baris — ditentukan
     * lead_field_definitions.is_sensitive saat baris ini dibuat (lihat LeadIntakeService).
     * Field sensitif TIDAK PERNAH ditulis ke kolom "value" plaintext.
     */
    public function up(): void
    {
        Schema::create('lead_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_field_definition_id')->constrained()->cascadeOnDelete();

            $table->text('value')->nullable();
            $table->text('value_encrypted')->nullable();

            $table->timestamps();

            $table->unique(['lead_id', 'lead_field_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_field_values');
    }
};
