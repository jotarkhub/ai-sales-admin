<?php

namespace App\Http\Requests\Platform;

use App\Models\IntegrationCredential;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Semua field nullable dengan sengaja — form ini dipakai untuk update SEBAGIAN kredensial
 * (mis. cuma ganti token setelah rotasi, tanpa perlu ketik ulang phone_number_id). Lihat
 * App\Services\WhatsApp\WhatsAppCredentialManager::save() — field kosong dilewati, tidak
 * menimpa nilai tersimpan.
 */
class UpdateWhatsAppCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            IntegrationCredential::WHATSAPP_KEY_TOKEN => ['nullable', 'string', 'max:1000'],
            IntegrationCredential::WHATSAPP_KEY_PHONE_NUMBER_ID => ['nullable', 'string', 'max:255'],
            IntegrationCredential::WHATSAPP_KEY_APP_SECRET => ['nullable', 'string', 'max:255'],
            IntegrationCredential::WHATSAPP_KEY_VERIFY_TOKEN => ['nullable', 'string', 'max:255'],
        ];
    }
}
