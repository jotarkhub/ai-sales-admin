<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BusinessConfigurationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeItemController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadFieldDefinitionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Role apa pun yang valid boleh melihat dashboard placeholder ini.
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('role:admin,supervisor,agent')
        ->name('dashboard');

    // Otorisasi granular (view vs update) ditegakkan oleh BusinessPolicy di controller,
    // middleware role di sini hanya gerbang pertama supaya non-staff tidak bisa masuk sama sekali.
    Route::middleware('role:admin,supervisor,agent')->group(function () {
        Route::get('pengaturan/bisnis', [BusinessConfigurationController::class, 'edit'])->name('business.edit');
        Route::put('pengaturan/bisnis', [BusinessConfigurationController::class, 'update'])->name('business.update');

        // Form builder — field custom per bisnis (mis. No KTP, Nama Bapak, dst. untuk klien pembiayaan).
        Route::get('pengaturan/field-lead', [LeadFieldDefinitionController::class, 'index'])->name('lead-fields.index');
        Route::post('pengaturan/field-lead', [LeadFieldDefinitionController::class, 'store'])->name('lead-fields.store');
        Route::get('pengaturan/field-lead/{leadField}/edit', [LeadFieldDefinitionController::class, 'edit'])->name('lead-fields.edit');
        Route::put('pengaturan/field-lead/{leadField}', [LeadFieldDefinitionController::class, 'update'])->name('lead-fields.update');
        Route::patch('pengaturan/field-lead/{leadField}/toggle', [LeadFieldDefinitionController::class, 'toggleActive'])->name('lead-fields.toggle');

        // Dashboard lead (Fase 5) — daftar, detail, ubah status. Konfirmasi "won" otorisasinya
        // lebih ketat (admin/supervisor), ditegakkan di LeadPolicy::confirmWon, bukan di sini.
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
        Route::post('leads/{lead}/confirm-won', [LeadController::class, 'confirmWon'])->name('leads.confirm-won');

        // Percakapan & human takeover (Fase 5b). Lihat docs/ARCHITECTURE.md #8 — state machine.
        Route::get('percakapan/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::post('percakapan/{conversation}/takeover', [ConversationController::class, 'takeover'])->name('conversations.takeover');
        Route::post('percakapan/{conversation}/release', [ConversationController::class, 'release'])->name('conversations.release');

        // Knowledge Base (Fase 5c) — sumber jawaban AI. Tulis/publikasi dibatasi admin/supervisor,
        // ditegakkan di KnowledgeItemPolicy::manage, bukan di middleware role di sini.
        Route::get('knowledge', [KnowledgeItemController::class, 'index'])->name('knowledge.index');
        Route::post('knowledge', [KnowledgeItemController::class, 'store'])->name('knowledge.store');
        Route::get('knowledge/{item}/edit', [KnowledgeItemController::class, 'edit'])->name('knowledge.edit');
        Route::put('knowledge/{item}', [KnowledgeItemController::class, 'update'])->name('knowledge.update');
        Route::patch('knowledge/{item}/toggle-publish', [KnowledgeItemController::class, 'togglePublish'])->name('knowledge.toggle-publish');
    });
});
