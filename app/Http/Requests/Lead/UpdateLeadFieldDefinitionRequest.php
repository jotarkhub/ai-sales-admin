<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::enum(LeadFieldType::class)],
            'is_required' => ['boolean'],
            'is_sensitive' => ['boolean'],
            'options_text' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            // key TIDAK termasuk di sini secara sengaja — immutable setelah dibuat (lihat
            // LeadFieldDefinitionController::update).
        ];
    }
}
