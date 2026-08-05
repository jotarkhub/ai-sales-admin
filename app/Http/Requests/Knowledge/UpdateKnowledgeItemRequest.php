<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\KnowledgeItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(KnowledgeItemStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'expiry_date.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal berlaku.',
        ];
    }
}
