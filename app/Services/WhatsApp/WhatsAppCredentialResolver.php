<?php

namespace App\Services\WhatsApp;

use App\Models\Business;
use App\Models\IntegrationCredential;

/**
 * Baca kredensial WhatsApp AKTIF milik satu bisnis dari integration_credentials (Fase 8b).
 * Dipakai App\Services\WhatsApp\MetaWhatsAppProvider saat mengirim — SATU-SATUNYA tempat yang
 * tahu cara menerjemahkan "bisnis mana" menjadi "token & phone_number_id mana". Tidak pernah
 * membaca config('services.whatsapp.token'/'phone_number_id') global lagi — itu peninggalan
 * sebelum multi-tenant, satu token untuk semua bisnis tidak masuk akal lagi.
 *
 * api_version boleh tidak diisi per bisnis -> fallback ke WHATSAPP_API_VERSION global (jarang
 * beda antar klien, cuma versi Graph API Meta yang dipakai).
 */
class WhatsAppCredentialResolver
{
    public function resolve(Business $business): ?WhatsAppCredentials
    {
        $rows = IntegrationCredential::query()
            ->where('business_id', $business->id)
            ->where('provider', IntegrationCredential::PROVIDER_WHATSAPP)
            ->where('is_active', true)
            ->get()
            ->keyBy('credential_key');

        $token = $rows->get(IntegrationCredential::WHATSAPP_KEY_TOKEN)?->encrypted_value;
        $phoneNumberId = $rows->get(IntegrationCredential::WHATSAPP_KEY_PHONE_NUMBER_ID)?->encrypted_value;

        if (blank($token) || blank($phoneNumberId)) {
            return null;
        }

        return new WhatsAppCredentials(
            token: $token,
            phoneNumberId: $phoneNumberId,
            apiVersion: config('services.whatsapp.api_version', 'v20.0'),
        );
    }
}
