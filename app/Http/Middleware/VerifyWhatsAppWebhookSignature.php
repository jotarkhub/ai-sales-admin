<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi bahwa POST webhook benar-benar dari Meta, lewat HMAC-SHA256 atas raw body
 * memakai App Secret (WHATSAPP_APP_SECRET — beda dari access token). Header yang
 * diharapkan: X-Hub-Signature-256: sha256=<hex hmac>. Pola sama seperti
 * App\Http\Middleware\VerifyLeadIntakeSignature.
 */
class VerifyWhatsAppWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.whatsapp.app_secret');

        if (blank($secret)) {
            // Belum dikonfigurasi -> tolak semua request daripada diam-diam menerima tanpa
            // verifikasi (lihat docs/STATUS.md: CREDENTIAL_REQUIRED).
            abort(503, 'WhatsApp webhook belum dikonfigurasi (WHATSAPP_APP_SECRET kosong).');
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            abort(401, 'Signature tidak ada atau format salah.');
        }

        $providedSignature = substr($header, strlen('sha256='));
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            abort(401, 'Signature tidak valid.');
        }

        return $next($request);
    }
}
