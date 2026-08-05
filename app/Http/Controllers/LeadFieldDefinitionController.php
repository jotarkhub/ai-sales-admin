<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentBusiness;
use App\Http\Requests\Lead\StoreLeadFieldDefinitionRequest;
use App\Http\Requests\Lead\UpdateLeadFieldDefinitionRequest;
use App\Models\LeadFieldDefinition;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Form builder — memungkinkan tiap bisnis mendefinisikan field lead tambahan sendiri
 * (mis. klien konsultan LPK menambah "No KTP Pemohon", "Nama Bapak", dst.) tanpa perlu
 * migration baru. Lihat docs/ARCHITECTURE.md bagian Custom Lead Fields.
 */
class LeadFieldDefinitionController extends Controller
{
    use ResolvesCurrentBusiness;

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function index(): View
    {
        $this->authorize('viewAny', LeadFieldDefinition::class);

        $business = $this->currentBusiness();
        $fields = $business->leadFieldDefinitions()->orderBy('sort_order')->orderBy('id')->get();

        return view('lead-fields.index', ['business' => $business, 'fields' => $fields]);
    }

    public function store(StoreLeadFieldDefinitionRequest $request): RedirectResponse
    {
        $this->authorize('manage', LeadFieldDefinition::class);

        $business = $this->currentBusiness();
        $data = $request->validated();

        $definition = $business->leadFieldDefinitions()->create([
            'key' => $this->generateUniqueKey($business->id, $data['label']),
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'is_required' => $request->boolean('is_required'),
            'is_sensitive' => $request->boolean('is_sensitive'),
            'options' => $this->parseOptions($data['options_text'] ?? null),
            'sort_order' => (int) ($business->leadFieldDefinitions()->max('sort_order') ?? 0) + 10,
            'is_active' => true,
        ]);

        $this->auditLog->record(
            action: 'lead_field_definition.created',
            subject: $definition,
            after: $definition->only(['key', 'label', 'field_type', 'is_required', 'is_sensitive']),
            actor: $request->user(),
            actorType: 'user',
        );

        return redirect()->route('lead-fields.index')->with('status', 'Field baru berhasil ditambahkan.');
    }

    public function edit(LeadFieldDefinition $leadField): View
    {
        $this->authorize('manage', $leadField);

        return view('lead-fields.edit', ['leadField' => $leadField]);
    }

    public function update(UpdateLeadFieldDefinitionRequest $request, LeadFieldDefinition $leadField): RedirectResponse
    {
        $this->authorize('manage', $leadField);

        $before = $leadField->only(['label', 'field_type', 'is_required', 'is_sensitive', 'sort_order']);
        $data = $request->validated();

        $leadField->update([
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'is_required' => $request->boolean('is_required'),
            'is_sensitive' => $request->boolean('is_sensitive'),
            'options' => $this->parseOptions($data['options_text'] ?? null),
            'sort_order' => $data['sort_order'],
        ]);

        $this->auditLog->record(
            action: 'lead_field_definition.updated',
            subject: $leadField,
            before: $before,
            after: $leadField->fresh()->only(array_keys($before)),
            actor: $request->user(),
            actorType: 'user',
        );

        return redirect()->route('lead-fields.index')->with('status', 'Field berhasil diperbarui.');
    }

    public function toggleActive(LeadFieldDefinition $leadField): RedirectResponse
    {
        $this->authorize('manage', $leadField);

        $before = ['is_active' => $leadField->is_active];
        $leadField->update(['is_active' => ! $leadField->is_active]);

        $this->auditLog->record(
            action: 'lead_field_definition.toggled',
            subject: $leadField,
            before: $before,
            after: ['is_active' => $leadField->is_active],
            actor: request()->user(),
            actorType: 'user',
        );

        return redirect()->route('lead-fields.index')->with(
            'status',
            $leadField->is_active ? 'Field diaktifkan kembali.' : 'Field dinonaktifkan.'
        );
    }

    private function generateUniqueKey(int $businessId, string $label): string
    {
        $base = Str::slug($label, '_');
        $key = $base;
        $suffix = 2;

        while (LeadFieldDefinition::where('business_id', $businessId)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix;
            $suffix++;
        }

        return $key;
    }

    private function parseOptions(?string $optionsText): ?array
    {
        if (blank($optionsText)) {
            return null;
        }

        $lines = array_filter(array_map('trim', explode("\n", $optionsText)));

        return array_values($lines) ?: null;
    }
}
