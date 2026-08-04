<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * encrypted_value disimpan lewat Laravel Crypt (app key), BUKAN plaintext. Nilai asli
     * tidak pernah tampil di log/UI kecuali dalam bentuk masked. Lihat App\Models\IntegrationCredential
     * yang akan pakai cast 'encrypted'.
     */
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('provider'); // whatsapp | openai | google
            $table->string('credential_key'); // e.g. access_token, phone_number_id, api_key
            $table->text('encrypted_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Nama constraint diberi eksplisit & dipendekkan — nama otomatis Laravel
            // ("integration_credentials_business_id_provider_credential_key_unique", 66 char)
            // melebihi batas identifier MySQL (64 char) dan gagal di CI walau lolos di SQLite.
            $table->unique(['business_id', 'provider', 'credential_key'], 'integration_credentials_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
