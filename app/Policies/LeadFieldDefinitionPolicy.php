<?php

namespace App\Policies;

use App\Models\LeadFieldDefinition;
use App\Models\Role;
use App\Models\User;

class LeadFieldDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Hanya admin yang boleh mengubah struktur form lead — field ini dipakai di seluruh pipeline intake. */
    public function manage(User $user, ?LeadFieldDefinition $definition = null): bool
    {
        return $user->is_active && $user->hasRole(Role::ADMIN);
    }
}
