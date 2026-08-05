<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeItemStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentBusiness;
use App\Http\Requests\Knowledge\StoreKnowledgeItemRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeItemRequest;
use App\Models\KnowledgeItem;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Knowledge Base (Fase 5c) — satu-satunya sumber jawaban yang boleh dipakai AI lewat
 * KnowledgeItem::scopeUsableByAi() (status published + effective/expiry masih berlaku).
 * Menulis/mempublikasikan sengaja dibatasi ke admin/supervisor, lihat KnowledgeItemPolicy.
 */
class KnowledgeItemController extends Controller
{
    use ResolvesCurrentBusiness;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function index(): View
    {
        $this->authorize('viewAny', KnowledgeItem::class);

        $business = $this->currentBusiness();
        $items = $business->knowledgeItems()
            ->with('owner')
            ->orderBy('category')
            ->orderByDesc('priority')
            ->get();

        return view('knowledge.index', [
            'business' => $business,
            'items' => $items,
            'statuses' => KnowledgeItemStatus::cases(),
        ]);
    }

    public function store(StoreKnowledgeItemRequest $request): RedirectResponse
    {
        $this->authorize('manage', KnowledgeItem::class);

        $business = $this->currentBusiness();
        $data = $request->validated();

        $item = $business->knowledgeItems()->create(array_merge($data, [
            'owner_id' => $request->user()->id,
        ]));

        $this->auditLog->record(
            action: 'knowledge_item.created',
            subject: $item,
            after: $item->only(['category', 'title', 'status', 'priority']),
            actor: $request->user(),
            actorType: 'user',
        );

        return redirect()->route('knowledge.index')->with('status', 'Knowledge item baru ditambahkan.');
    }

    public function edit(KnowledgeItem $item): View
    {
        $this->authorize('manage', $item);

        return view('knowledge.edit', ['item' => $item]);
    }

    public function update(UpdateKnowledgeItemRequest $request, KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);

        $before = $item->only(['category', 'title', 'content', 'status', 'priority', 'source', 'effective_date', 'expiry_date']);

        $item->update($request->validated());

        $this->auditLog->record(
            action: 'knowledge_item.updated',
            subject: $item,
            before: $before,
            after: $item->fresh()->only(array_keys($before)),
            actor: $request->user(),
            actorType: 'user',
        );

        return redirect()->route('knowledge.index')->with('status', 'Knowledge item berhasil diperbarui.');
    }

    /** Toggle draft <-> published. Item draft TIDAK PERNAH dipakai AI (lihat scopeUsableByAi). */
    public function togglePublish(KnowledgeItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);

        $before = ['status' => $item->status->value];
        $newStatus = $item->status === KnowledgeItemStatus::Published
            ? KnowledgeItemStatus::Draft
            : KnowledgeItemStatus::Published;

        $item->update(['status' => $newStatus]);

        $this->auditLog->record(
            action: 'knowledge_item.status_changed',
            subject: $item,
            before: $before,
            after: ['status' => $newStatus->value],
            actor: request()->user(),
            actorType: 'user',
        );

        return redirect()->route('knowledge.index')->with(
            'status',
            $newStatus === KnowledgeItemStatus::Published ? 'Knowledge item dipublikasikan.' : 'Knowledge item ditarik ke draft.'
        );
    }
}
