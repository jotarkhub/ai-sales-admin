<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handshake verifikasi awal saat webhook didaftarkan di Meta for Developers.
     * Meta kirim GET dengan query hub.mode/hub.verify_token/hub.challenge — TAPI PHP
     * otomatis mengganti titik di nama parameter query jadi underscore saat parsing
     * superglobal $_GET, jadi yang benar-benar diterima di sini adalah hub_mode/
     * hub_verify_token/hub_challenge (perilaku PHP, bukan Laravel). Kita wajib membalas
     * persis nilai challenge kalau verify_token cocok.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');
        $expected = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && filled($expected) && hash_equals((string) $expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403, 'Verifikasi webhook WhatsApp gagal — verify_token tidak cocok.');
    }

    /**
     * Terima event pesan/status dari Meta. Signature sudah divalidasi middleware
     * verify.whatsapp_webhook_signature sebelum sampai sini. Selalu balas 200 begitu
     * payload diterima & disimpan — kegagalan per-item dicatat di webhook_events, bukan
     * bikin Meta retry seluruh batch berulang-ulang.
     */
    public function receive(Request $request, WhatsAppWebhookService $service): JsonResponse
    {
        $service->handle($request->all());

        return response()->json(['status' => 'received']);
    }
}
