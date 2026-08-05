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

    // Webhook WhatsApp Cloud API. GET untuk handshake verifikasi (tanpa signature — belum
    // ada body untuk di-HMAC), POST untuk event pesan/status (wajib signature valid).
    Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
        ->name('api.v1.whatsapp.webhook.verify');
    Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('verify.whatsapp_webhook_signature')
        ->name('api.v1.whatsapp.webhook.receive');
});
