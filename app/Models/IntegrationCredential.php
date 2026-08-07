<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCredential extends Model
{
    public const PROVIDER_WHATSAPP = 'whatsapp';

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_GOOGLE = 'google';

    // credential_key untuk provider=whatsapp (Fase 8b). Tiap bisnis punya App Meta sendiri
    // (keputusan arsitektur: lihat riwayat percakapan Fase 8), jadi keempatnya per-bisnis.
    public const WHATSAPP_KEY_TOKEN = 'token';

    public const WHATSAPP_KEY_PHONE_NUMBER_ID = 'phone_number_id';

    public const WHATSAPP_KEY_APP_SECRET = 'app_secret';

    public const WHATSAPP_KEY_VERIFY_TOKEN = 'verify_token';

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
