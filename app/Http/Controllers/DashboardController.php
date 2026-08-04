<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Placeholder dashboard — memastikan alur login + role middleware benar-benar berfungsi
     * end-to-end. Isi dashboard sesungguhnya (lead baru, percakapan aktif, dst.) dibangun
     * di Fase 5, lihat docs/ARCHITECTURE.md dan docs/STATUS.md.
     */
    public function index(Request $request): View
    {
        return view('dashboard', ['user' => $request->user()]);
    }
}
