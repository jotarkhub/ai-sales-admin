<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentBusiness;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dashboard admin — daftar & detail lead (Fase 5). Ubah status di sini SELALU tercatat
 * di lead_activities + audit_logs; transisi ke "won" sengaja dipisah ke endpoint/otorisasi
 * sendiri (confirmWon) supaya tidak bisa "kebablasan" lewat form status umum.
 */
class LeadController extends Controller
{
    use ResolvesCurrentBusiness;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $business = $this->currentBusiness();
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('q')->toString() ?: null;

        $leads = $business->leads()
            ->with(['leadSource', 'interestedProduct', 'assignedAdmin'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('leads.index', [
            'business' => $business,
            'leads' => $leads,
            'statuses' => LeadStatus::cases(),
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load([
            'leadSource',
            'interestedProduct',
            'assignedAdmin',
            'wonConfirmedBy',
            'tags',
            'fieldValues.definition',
            'activities' => fn ($query) => $query->orderByDesc('created_at'),
            'conversations' => fn ($query) => $query->orderByDesc('last_message_at'),
        ]);

        return view('leads.show', [
            'lead' => $lead,
            'statuses' => collect(LeadStatus::cases())
                ->reject(fn (LeadStatus $status) => in_array($status, LeadStatus::requiresAdminConfirmation(), true)),
            'canConfirmWon' => $request->user()->can('confirmWon', $lead),
        ]);
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $newStatus = LeadStatus::from($request->validated('status'));
        $before = ['status' => $lead->status->value];

        if ($lead->status === $newStatus) {
            return redirect()->route('leads.show', $lead)->with('status', 'Status tidak berubah.');
        }

        $lead->update(['status' => $newStatus]);

        $this->auditLog->record(
            action: 'lead.status_changed',
            subject: $lead,
            before: $before,
            after: ['status' => $newStatus->value],
            actor: $request->user(),
            actorType: 'user',
        );

        LeadActivity::create([
            'business_id' => $lead->business_id,
            'lead_id' => $lead->id,
            'type' => 'status_changed',
            'description' => "Status diubah dari \"{$before['status']}\" ke \"{$newStatus->value}\" oleh {$request->user()->name}.",
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
            'metadata' => ['from' => $before['status'], 'to' => $newStatus->value],
        ]);

        return redirect()->route('leads.show', $lead)->with('status', 'Status lead diperbarui.');
    }

    /**
     * Satu-satunya jalur resmi lead berpindah ke status "won". Tidak pernah dipanggil oleh
     * AI/sistem — hanya lewat aksi eksplisit admin/supervisor via tombol di dashboard.
     */
    public function confirmWon(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('confirmWon', $lead);

        if ($lead->status === LeadStatus::Won) {
            return redirect()->route('leads.show', $lead)->with('status', 'Lead ini sudah berstatus won.');
        }

        $before = ['status' => $lead->status->value, 'won_confirmed_by' => $lead->won_confirmed_by];

        $lead->update([
            'status' => LeadStatus::Won,
            'won_confirmed_by' => $request->user()->id,
            'won_confirmed_at' => now(),
        ]);

        $this->auditLog->record(
            action: 'lead.won_confirmed',
            subject: $lead,
            before: $before,
            after: ['status' => LeadStatus::Won->value, 'won_confirmed_by' => $request->user()->id],
            actor: $request->user(),
            actorType: 'user',
        );

        LeadActivity::create([
            'business_id' => $lead->business_id,
            'lead_id' => $lead->id,
            'type' => 'won_confirmed',
            'description' => "Lead dikonfirmasi WON oleh {$request->user()->name}.",
            'actor_type' => 'user',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('leads.show', $lead)->with('status', 'Lead dikonfirmasi sebagai won.');
    }
}
