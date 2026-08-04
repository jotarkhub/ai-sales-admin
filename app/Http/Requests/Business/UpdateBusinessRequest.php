<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya dicek policy di controller (Gate::authorize), ini hanya
        // lapisan pertama supaya request langsung ditolak kalau belum login.
        return $this->user() !== null;
    }

    /**
     * Field JSON (operating_hours, ai_authority_limit, dst.) dikirim sebagai string JSON
     * mentah dari form (textarea) untuk MVP — UI form dinamis per-field ada di Fase 5.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'assistant_name' => ['nullable', 'string', 'max:255'],
            'assistant_identity' => ['nullable', 'string', 'max:2000'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'timezone'],
            'payment_terms' => ['nullable', 'string', 'max:5000'],
            'refund_policy' => ['nullable', 'string', 'max:5000'],
            'opt_out_instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],

            'operating_hours' => ['nullable', 'json'],
            'ai_authority_limit' => ['nullable', 'json'],
            'escalation_rules' => ['nullable', 'json'],
            'message_templates' => ['nullable', 'json'],
            'follow_up_schedule' => ['nullable', 'json'],
        ];
    }

    public function messages(): array
    {
        return [
            'timezone' => 'Zona waktu tidak valid.',
            '*.json' => 'Format harus JSON yang valid.',
        ];
    }

    /**
     * Field JSON di-decode di sini supaya controller & test langsung menerima array,
     * konsisten dengan cast 'array' di model Business.
     */
    public function validatedWithDecodedJson(): array
    {
        $data = $this->validated();

        foreach (['operating_hours', 'ai_authority_limit', 'escalation_rules', 'message_templates', 'follow_up_schedule'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = json_decode((string) $data[$field], true);
            } elseif (array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
