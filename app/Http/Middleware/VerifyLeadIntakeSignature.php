<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi bahwa request datang dari Google Apps Script yang sah, lewat HMAC-SHA256
 * atas raw body memakai shared secret LEAD_INTAKE_SECRET. Header yang diharapkan:
 * X-Signature: sha256=<hex hmac>
 *
 * Kenapa HMAC (bukan cuma secret polos di header)? Supaya payload tidak bisa diubah
 * di tengah jalan tanpa signature ikut berubah, dan secret tidak dikirim mentah tiap request.
 */
class VerifyLeadIntakeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.lead_intake.secret');

        if (blank($secret)) {
            // Kredensial belum dikonfigurasi — tolak semua request daripada diam-diam
            // menerima tanpa verifikasi (lihat docs/STATUS.md: CREDENTIAL_REQUIRED).
            abort(503, 'Lead intake belum dikonfigurasi (LEAD_INTAKE_SECRET kosong).');
        }

        $header = (string) $request->header('X-Signature', '');

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
