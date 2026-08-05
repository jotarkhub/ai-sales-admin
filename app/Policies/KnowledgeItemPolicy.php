<?php

namespace App\Policies;

use App\Models\KnowledgeItem;
use App\Models\Role;
use App\Models\User;

class KnowledgeItemPolicy
{
    /** Semua staf aktif boleh melihat knowledge base bisnisnya. */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Hanya admin/supervisor yang boleh menulis/mengubah/mempublikasikan knowledge item —
     * ini sumber jawaban yang langsung dipakai AI (lihat KnowledgeItem::scopeUsableByAi),
     * jadi kualitas & keakuratannya dijaga di level kurator, bukan semua agent.
     */
    public function manage(User $user, ?KnowledgeItem $item = null): bool
    {
        return $user->is_active && ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPERVISOR));
    }
}
