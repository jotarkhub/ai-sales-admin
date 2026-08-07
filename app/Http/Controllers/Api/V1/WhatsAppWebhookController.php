<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\WhatsApp\WhatsAppCredentialResolver;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly WhatsAppCredentialResolver $credentials) {}

    /**
     * Handshake verifikasi awal saat webhook didaftarkan di Meta for Developers, untuk bisnis
     * ini (Fase 8c — Verify Token per bisnis, bukan global). Meta kirim GET dengan query
     * hub.mode/hub.verify_token/hub.challenge — TAPI PHP otomatis mengganti titik di nama
     * parameter query jadi underscore saat parsing superglobal $_GET, jadi yang benar-benar
     * diterima di sini adalah hub_mode/hub_verify_token/hub_challenge (perilaku PHP, bukan
     * Laravel). Kita wajib membalas persis nilai challenge kalau verify_token cocok.
     */
    public function verify(Request $request, Business $business): Response
    {
        if (! $business->is_active) {
            abort(403, 'Bisnis ini tidak aktif.');
        }

        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        $secrets = $this->credentials->resolveWebhookSecrets($business);

        if ($secrets !== null && $mode === 'subscribe' && hash_equals($secrets->verifyToken, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403, 'Verifikasi webhook WhatsApp gagal — verify_token tidak cocok.');
    }

    /**
     * Terima event pesan/status dari Meta untuk bisnis ini. Signature sudah divalidasi
     * middleware verify.whatsapp_webhook_signature sebelum sampai sini. Selalu balas 200
     * begitu payload diterima & disimpan — kegagalan per-item dicatat di webhook_events,
     * bukan bikin Meta retry seluruh batch berulang-ulang.
     */
    public function receive(Request $request, Business $business, WhatsAppWebhookService $service): JsonResponse
    {
        $service->handle($request->all(), $business);

        return response()->json(['status' => 'received']);
    }
}
