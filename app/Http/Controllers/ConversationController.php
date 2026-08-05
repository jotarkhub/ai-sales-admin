<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\LeadActivity;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dashboard admin — lihat percakapan & human takeover (Fase 5b). Sesuai state machine di
 * docs/ARCHITECTURE.md #8: selama status human_takeover, AI TIDAK BOLEH membalas otomatis.
 * Modul ini hanya mengubah status conversation; enforcement "AI berhenti membalas" ada di
 * sisi Conversation Engine (Fase 4) yang wajib mengecek status ini sebelum membalas.
 */
class ConversationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function show(Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $conversation->load([
            'lead',
            'assignedAdmin',
            'messages' => fn ($query) => $query->orderBy('created_at'),
            'escalations' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return view('conversations.show', ['conversation' => $conversation]);
    }

    public function takeover(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('manage', $conversation);

        if ($conversation->status !== ConversationStatus::AiActive) {
            return back()->with('status', 'Percakapan ini sudah tidak dalam status AI aktif.');
        }

        $before = ['status' => $conversation->status->value, 'assigned_admin_id' => $conversation->assigned_admin_id];

        $conversation->update([
            'status' => ConversationStatus::HumanTakeover,
            'assigned_admin_id' => $request->user()->id,
        ]);

        $conversation->escalations()
            ->where('status', Escalation::STATUS_OPEN)
            ->update([
                'status' => Escalation::STATUS_CLAIMED,
                'claimed_by' => $request->user()->id,
                'claimed_at' => now(),
            ]);

        $this->auditLog->record(
            action: 'conversation.takeover',
            subject: $conversation,
            before: $before,
            after: ['status' => ConversationStatus::HumanTakeover->value, 'assigned_admin_id' => $request->user()->id],
            actor: $request->user(),
            actorType: 'user',
        );

        LeadActivity::create([
            'business_id' => $conversation->business_id,
            'lead_id' => $conversation->lead_id,
            'type' => 'conversation_takeover',
            'description' => "Percakapan diambil alih oleh {$request->user()->name}. AI berhenti membalas otomatis.",
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('conversations.show', $conversation)->with('status', 'Percakapan berhasil diambil alih.');
    }

    public function release(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('manage', $conversation);

        if ($conversation->status !== ConversationStatus::HumanTakeover) {
            return back()->with('status', 'Percakapan ini tidak sedang diambil alih.');
        }

        $before = ['status' => $conversation->status->value, 'assigned_admin_id' => $conversation->assigned_admin_id];

        $conversation->update([
            'status' => ConversationStatus::AiActive,
            'assigned_admin_id' => null,
        ]);

        $this->auditLog->record(
            action: 'conversation.released_to_ai',
            subject: $conversation,
            before: $before,
            after: ['status' => ConversationStatus::AiActive->value, 'assigned_admin_id' => null],
            actor: $request->user(),
            actorType: 'user',
        );

        LeadActivity::create([
            'business_id' => $conversation->business_id,
            'lead_id' => $conversation->lead_id,
            'type' => 'conversation_released_to_ai',
            'description' => "Percakapan dikembalikan ke AI oleh {$request->user()->name}.",
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('conversations.show', $conversation)->with('status', 'Percakapan dikembalikan ke AI.');
    }
}
