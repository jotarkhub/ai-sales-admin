<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BusinessConfigurationController;
use App\Http\Controllers\DashboardController;
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
    });
});
