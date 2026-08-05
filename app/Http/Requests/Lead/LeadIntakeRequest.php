<?php

namespace App\Http\Requests\Lead;

use App\Models\Business;
use App\Services\Lead\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\Validator;
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

            // Jawaban field custom (form builder per bisnis) — lihat App\Models\LeadFieldDefinition.
            'custom_answers' => ['sometimes', 'array'],
        ];
    }

    /**
     * Field custom yang wajib (lead_field_definitions.is_required) divalidasi di sini karena
     * daftarnya dinamis per bisnis (tersimpan di database), bukan aturan statis.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $business = Business::query()->where('is_active', true)->first();

            if (! $business) {
                return;
            }

            $customAnswers = (array) $this->input('custom_answers', []);

            $requiredFields = $business->leadFieldDefinitions()
                ->active()
                ->where('is_required', true)
                ->get();

            foreach ($requiredFields as $field) {
                $value = $customAnswers[$field->key] ?? null;

                if ($value === null || $value === '') {
                    $validator->errors()->add(
                        "custom_answers.{$field->key}",
                        "Field \"{$field->label}\" wajib diisi."
                    );
                }
            }
        });
    }
}
