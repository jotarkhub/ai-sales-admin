<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Exceptions\WhatsAppNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Implementasi nyata WhatsApp Business Cloud API (Meta) — CREDENTIAL_REQUIRED sampai
 * WHATSAPP_TOKEN & WHATSAPP_PHONE_NUMBER_ID diisi. Tidak pernah dipanggil selama
 * WHATSAPP_PROVIDER=fake (lihat App\Support\ProviderGuard).
 *
 * Referensi: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class MetaWhatsAppProvider implements WhatsAppProviderContract
{
    public function sendTextMessage(string $to, string $body): WhatsAppSendResult
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (blank($token) || blank($phoneNumberId)) {
            throw new WhatsAppNotConfiguredException(
                'Kredensial WhatsApp belum dikonfigurasi. Isi WHATSAPP_TOKEN dan '.
                'WHATSAPP_PHONE_NUMBER_ID di .env (lihat docs/ARCHITECTURE.md #9), atau '.
                'pakai WHATSAPP_PROVIDER=fake untuk lingkungan testing.'
            );
        }

        $version = config('services.whatsapp.api_version', 'v20.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $response = Http::withToken($token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        if ($response->successful()) {
            return WhatsAppSendResult::success(
                providerMessageId: $response->json('messages.0.id') ?? 'unknown',
                rawResponse: $response->json() ?? [],
            );
        }

        return WhatsAppSendResult::failure(
            errorMessage: $response->json('error.message') ?? "HTTP {$response->status()} tanpa pesan error dari Meta.",
            rawResponse: $response->json() ?? [],
        );
    }
}
