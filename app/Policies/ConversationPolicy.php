<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->is_active && $user->business_id === $conversation->business_id;
    }

    /**
     * Take over / kembalikan ke AI sengaja dibuka untuk semua staf aktif (bukan cuma
     * admin/supervisor) — siapa pun yang sedang menangani lead harus bisa segera
     * mengambil alih percakapan saat pelanggan butuh respons manusia. Beda dengan
     * konfirmasi "won" (LeadPolicy::confirmWon) yang memang dibatasi lebih ketat.
     */
    public function manage(User $user, Conversation $conversation): bool
    {
        return $user->is_active && $user->business_id === $conversation->business_id;
    }
}
