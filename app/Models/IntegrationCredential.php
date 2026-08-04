<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCredential extends Model
{
    public const PROVIDER_WHATSAPP = 'whatsapp';

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_GOOGLE = 'google';

    protected $fillable = [
        'business_id', 'provider', 'credential_key', 'encrypted_value', 'is_active',
        'expires_at', 'created_by',
    ];

    protected $hidden = ['encrypted_value'];

    protected function casts(): array
    {
        return [
            // Cast 'encrypted' Laravel otomatis enkripsi/dekripsi pakai APP_KEY — nilai asli
            // tidak pernah tersimpan sebagai plaintext di database.
            'encrypted_value' => 'encrypted',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
