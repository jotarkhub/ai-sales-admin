<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Staf aktif boleh melihat konfigurasi bisnisnya SENDIRI saja — sebelumnya method ini
     * true untuk staf mana pun tanpa cek business_id, aman waktu cuma ada satu bisnis tapi
     * jadi celah begitu ada bisnis kedua. Lihat App\Http\Controllers\Concerns\ResolvesCurrentBusiness.
     */
    public function view(User $user, Business $business): bool
    {
        return $user->is_active && $user->business_id === $business->id;
    }

    /**
     * Hanya admin bisnis yang sama yang boleh mengubah konfigurasi — batas kewenangan AI,
     * aturan eskalasi, kebijakan refund, dst. adalah pengaturan sensitif. Platform owner
     * SENGAJA tidak diberi akses lewat policy ini — kelola tenant lewat PlatformBusinessController,
     * bukan mengubah konten operasional bisnis klien.
     */
    public function update(User $user, Business $business): bool
    {
        return $user->is_active && $user->business_id === $business->id && $user->hasRole(Role::ADMIN);
    }
}
