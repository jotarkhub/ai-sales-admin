<?php

use App\Http\Controllers\Api\V1\LeadIntakeController;
use Illuminate\Support\Facades\Route;

// Semua endpoint API di sini otomatis berprefix /api (lihat bootstrap/app.php).
// Versioned lewat prefix v1 — lihat docs/ARCHITECTURE.md untuk kontrak payload.
Route::prefix('v1')->group(function () {
    Route::post('leads/intake', [LeadIntakeController::class, 'store'])
        ->middleware('verify.lead_intake_signature')
        ->name('api.v1.leads.intake');
});
