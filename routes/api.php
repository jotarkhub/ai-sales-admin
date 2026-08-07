<?php

use App\Http\Controllers\Api\V1\LeadIntakeController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Semua endpoint API di sini otomatis berprefix /api (lihat bootstrap/app.php).
// Versioned lewat prefix v1 — lihat docs/ARCHITECTURE.md untuk kontrak payload.
Route::prefix('v1')->group(function () {
    Route::post('leads/intake', [LeadIntakeController::class, 'store'])
        ->middleware('verify.lead_intake_signature')
        ->name('api.v1.leads.intake');

    // Webhook WhatsApp Cloud API — per bisnis (Fase 8c), bukan satu URL global. Konsekuensi
    // keputusan "tiap klien App Meta sendiri": tiap bisnis punya App Secret sendiri, jadi URL
    // webhook yang didaftarkan ke Meta juga harus beda per bisnis. {business:webhook_slug}
    // dipilih daripada {business} (ID auto-increment) supaya URL publik ini tidak gampang
    // ditebak/diurut — lihat App\Models\Business::booted().
    //
    // GET untuk handshake verifikasi (tanpa signature — belum ada body untuk di-HMAC),
    // POST untuk event pesan/status (wajib signature valid).
    Route::get('whatsapp/webhook/{business:webhook_slug}', [WhatsAppWebhookController::class, 'verify'])
        ->name('api.v1.whatsapp.webhook.verify');
    Route::post('whatsapp/webhook/{business:webhook_slug}', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('verify.whatsapp_webhook_signature')
        ->name('api.v1.whatsapp.webhook.receive');
});
