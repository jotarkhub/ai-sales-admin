<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\Business;
use Illuminate\Support\Facades\Http;

/**
 * Implementasi nyata WhatsApp Business Cloud API (Meta) — CREDENTIAL_REQUIRED per bisnis
 * sampai token & phone_number_id bisnis itu diisi lewat panel platform owner (Fase 8b, lihat
 * App\Http\Controllers\PlatformBusinessController). Tidak pernah dipanggil selama
 * WHATSAPP_PROVIDER=fake (lihat App\Support\ProviderGuard).
 *
 * Referensi: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class MetaWhatsAppProvider implements WhatsAppProviderContract
{
    public function __construct(private readonly WhatsAppCredentialResolver $credentials) {}

    public function sendTextMessage(Business $business, string $to, string $body): WhatsAppSendResult
    {
        $credentials = $this->credentials->resolve($business);

        if ($credentials === null) {
            throw new WhatsAppNotConfiguredException(
                "Kredensial WhatsApp bisnis \"{$business->name}\" belum lengkap (token + phone_number_id). ".
                'Isi lewat panel platform owner (Pengaturan > Kredensial WhatsApp), atau pakai '.
                'WHATSAPP_PROVIDER=fake untuk lingkungan testing.'
            );
        }

        $url = "https://graph.facebook.com/{$credentials->apiVersion}/{$credentials->phoneNumberId}/messages";

        $response = Http::withToken($credentials->token)->post($url, [
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
