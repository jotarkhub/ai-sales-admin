<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BusinessConfigurationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * MVP: satu business aktif. Resolver ini adalah satu-satunya tempat yang tahu cara
     * menentukan "bisnis aktif saat ini" — kalau nanti multi-tenant, cukup ganti di sini
     * (mis. dari business_id di session admin yang login).
     */
    private function currentBusiness(): Business
    {
        return Business::query()->where('is_active', true)->firstOrFail();
    }

    public function edit(): View
    {
        $business = $this->currentBusiness();
        $this->authorize('view', $business);

        return view('business.edit', ['business' => $business]);
    }

    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = $this->currentBusiness();
        $this->authorize('update', $business);

        $before = $business->only([
            'name', 'assistant_name', 'assistant_identity', 'whatsapp_number', 'timezone',
            'payment_terms', 'refund_policy', 'opt_out_instructions', 'is_active',
            'operating_hours', 'ai_authority_limit', 'escalation_rules', 'message_templates',
            'follow_up_schedule',
        ]);

        $business->update($request->validatedWithDecodedJson());

        $this->auditLog->record(
            action: 'business.updated',
            subject: $business,
            before: $before,
            after: $business->fresh()->only(array_keys($before)),
            actor: $request->user(),
            actorType: 'user',
        );

        return back()->with('status', 'Konfigurasi bisnis berhasil disimpan.');
    }
}
