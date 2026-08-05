<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadFieldDefinitionRequest extends FormRequest
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
            // Textarea satu opsi per baris di form — dipecah jadi array di controller.
            'options_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
