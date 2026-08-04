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

            $table->unique(['business_id', 'provider', 'credential_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
