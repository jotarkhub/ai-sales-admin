<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Satu-satunya jalur resmi untuk menulis audit_logs. Semua perubahan status prospek,
 * konfigurasi, takeover, dan aksi penting lain WAJIB lewat service ini — bukan menulis
 * langsung ke model AuditLog di tempat lain (supaya format actor_type/subject_type konsisten).
 */
class AuditLogService
{
    /**
     * @param  Model  $subject  Entitas yang berubah (Lead, Business, Conversation, dst.)
     * @param  array|null  $before  State sebelum perubahan (null kalau aksi ini bukan "perubahan", mis. login)
     * @param  array|null  $after  State sesudah perubahan
     * @param  User|null  $actor  Aktor manusia. Kosongkan untuk actorType 'system'/'ai'.
     * @param  string  $actorType  'user' | 'system' | 'ai'
     */
    public function record(
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
        string $actorType = AuditLog::ACTOR_SYSTEM,
    ): AuditLog {
        $actor ??= Auth::user();

        if ($actor && $actorType === AuditLog::ACTOR_SYSTEM) {
            // Kalau ada user login tapi caller lupa set actorType, anggap ini aksi user.
            $actorType = AuditLog::ACTOR_USER;
        }

        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::header('User-Agent'),
            'created_at' => now(),
        ]);
    }

    /** Helper khusus untuk aksi yang dipicu sistem (job, scheduler), tanpa aktor manusia. */
    public function recordSystem(string $action, Model $subject, ?array $before = null, ?array $after = null): AuditLog
    {
        return $this->record($action, $subject, $before, $after, null, AuditLog::ACTOR_SYSTEM);
    }

    /** Helper khusus untuk keputusan yang dieksekusi berdasarkan rekomendasi AI. */
    public function recordAi(string $action, Model $subject, ?array $before = null, ?array $after = null): AuditLog
    {
        return $this->record($action, $subject, $before, $after, null, AuditLog::ACTOR_AI);
    }
}
