<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ];
    }

    /**
     * Status yang butuh konfirmasi admin eksplisit (saat ini hanya "won") tidak boleh
     * dicapai lewat endpoint update status umum ini — harus lewat endpoint confirmWon
     * yang otorisasinya lebih ketat. Ini menegakkan aturan "AI/staf biasa tidak bisa
     * menandai lead won begitu saja" di level request, bukan cuma di controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $status = $this->input('status');

            if ($status !== null && in_array(LeadStatus::tryFrom($status), LeadStatus::requiresAdminConfirmation(), true)) {
                $validator->errors()->add(
                    'status',
                    'Status "won" tidak bisa diset lewat form ini — gunakan tombol "Konfirmasi Won" khusus.'
                );
            }
        });
    }
}
