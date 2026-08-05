<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

class LeadPolicy
{
    /** Semua staf aktif boleh melihat daftar lead bisnisnya. */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->is_active && $user->business_id === $lead->business_id;
    }

    /** Ubah status umum (TIDAK termasuk "won" — itu lewat confirmWon). */
    public function update(User $user, Lead $lead): bool
    {
        return $user->is_active && $user->business_id === $lead->business_id;
    }

    /**
     * Konfirmasi lead "won" adalah aksi final yang tidak boleh dilakukan AI sama sekali
     * dan sengaja dibatasi ke admin/supervisor (bukan semua agent) karena berdampak
     * langsung ke pelaporan closing/komisi. Lihat docs/ARCHITECTURE.md — Lead State Machine.
     */
    public function confirmWon(User $user, Lead $lead): bool
    {
        return $user->is_active
            && $user->business_id === $lead->business_id
            && ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPERVISOR));
    }
}
