<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;

class BusinessPolicy
{
    /** Semua staf aktif boleh melihat konfigurasi bisnis. */
    public function view(User $user, Business $business): bool
    {
        return $user->is_active;
    }

    /**
     * Hanya admin yang boleh mengubah konfigurasi bisnis — batas kewenangan AI, aturan
     * eskalasi, kebijakan refund, dst. adalah pengaturan sensitif.
     */
    public function update(User $user, Business $business): bool
    {
        return $user->is_active && $user->hasRole(Role::ADMIN);
    }
}
