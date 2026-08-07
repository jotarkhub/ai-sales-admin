<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\WhatsApp\WhatsAppCredentialResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi bahwa POST webhook benar-benar dari Meta, lewat HMAC-SHA256 atas raw body
 * memakai App Secret bisnis yang bersangkutan (Fase 8c — App Secret per bisnis, bukan
 * WHATSAPP_APP_SECRET global lagi). Header yang diharapkan:
 * X-Hub-Signature-256: sha256=<hex hmac>. Pola sama seperti App\Http\Middleware\VerifyLeadIntakeSignature.
 *
 * $business diambil dari route model binding {business:webhook_slug} — middleware ini
 * WAJIB didaftarkan setelah SubstituteBindings jalan (perilaku default Laravel untuk route
 * middleware), jadi $request->route('business') di sini sudah berupa model, bukan string.
 */
class VerifyWhatsAppWebhookSignature
{
    public function __construct(private readonly WhatsAppCredentialResolver $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if (! $business instanceof Business) {
            abort(404, 'Bisnis tidak ditemukan.');
        }

        if (! $business->is_active) {
            abort(403, 'Bisnis ini tidak aktif.');
        }

        $secrets = $this->credentials->resolveWebhookSecrets($business);

        if ($secrets === null) {
            // Belum dikonfigurasi -> tolak semua request daripada diam-diam menerima tanpa
            // verifikasi (lihat docs/STATUS.md: CREDENTIAL_REQUIRED).
            abort(503, "Webhook WhatsApp bisnis \"{$business->name}\" belum dikonfigurasi (App Secret/Verify Token kosong).");
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            abort(401, 'Signature tidak ada atau format salah.');
        }

        $providedSignature = substr($header, strlen('sha256='));
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secrets->appSecret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            abort(401, 'Signature tidak valid.');
        }

        return $next($request);
    }
}
