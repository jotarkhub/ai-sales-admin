<?php

namespace App\Http\Requests\Lead;

use App\Services\Lead\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kontrak payload dari Google Apps Script. Lihat docs/ARCHITECTURE.md #3 (sequence diagram
 * form submission) dan nanti app-script/README.md (Fase 6) untuk skrip pengirimnya.
 */
class LeadIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi dilakukan oleh middleware verify.lead_intake_signature, bukan di sini.
        return true;
    }

    public function rules(): array
    {
        return [
            'external_submission_id' => ['required', 'string', 'max:191'],
            'submitted_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:32', function ($attribute, $value, $fail): void {
                if (! app(PhoneNumberNormalizer::class)->isValid((string) $value)) {
                    $fail('Nomor WhatsApp tidak valid atau formatnya tidak dikenali.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'interested_product' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'budget_estimate' => ['nullable', 'string', 'max:255'],
            'purchase_timeline' => ['nullable', 'string', 'max:255'],
            'needs_notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:100'],
            'consent_whatsapp' => ['required', 'boolean'],
            'raw_answers' => ['required', 'array'],
        ];
    }
}
