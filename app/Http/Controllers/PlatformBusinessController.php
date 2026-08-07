<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Contracts\View\View;

/**
 * Panel platform owner (Fase 8a) — lintas bisnis, bukan operasional satu bisnis. Dibatasi
 * middleware 'role:platform_owner' di routes/web.php, bukan lewat BusinessPolicy (policy itu
 * sengaja tetap khusus staf bisnis yang sama, lihat App\Policies\BusinessPolicy).
 *
 * Fase 8a baru menyediakan daftar bisnis (bukti akses lintas-tenant berfungsi & terisolasi
 * dengan benar). Form "Tambah Bisnis Baru" + kelola kredensial WhatsApp/AI per bisnis
 * menyusul di Fase 8b/8d — lihat docs/STATUS.md.
 */
class PlatformBusinessController extends Controller
{
    public function index(): View
    {
        $businesses = Business::query()
            ->withCount(['users', 'leads'])
            ->orderBy('name')
            ->get();

        return view('platform.businesses.index', ['businesses' => $businesses]);
    }
}
