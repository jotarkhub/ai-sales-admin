<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fase 8d — form platform owner mendaftarkan bisnis (tenant) baru sekaligus akun admin
 * pertamanya. Otorisasi sesungguhnya dicek middleware 'role:platform_owner' di routes/web.php
 * (bukan policy) — sama seperti PlatformBusinessController lainnya.
 */
class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'timezone' => 'Zona waktu tidak valid.',
            'admin_email.unique' => 'Email ini sudah dipakai akun lain.',
            'admin_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'admin_password.min' => 'Password minimal 8 karakter.',
        ];
    }
}
