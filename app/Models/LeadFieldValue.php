<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Jangan set $table->value / value_encrypted langsung dari luar — selalu lewat
 * LeadFieldValue::makeFor() supaya keputusan plaintext-vs-enkripsi konsisten dengan
 * lead_field_definitions.is_sensitive.
 */
class LeadFieldValue extends Model
{
    protected $fillable = ['lead_id', 'lead_field_definition_id', 'value', 'value_encrypted'];

    protected $hidden = ['value_encrypted'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(LeadFieldDefinition::class, 'lead_field_definition_id');
    }

    public static function makeFor(LeadFieldDefinition $definition, string $rawValue): array
    {
        if ($definition->is_sensitive) {
            return ['value' => null, 'value_encrypted' => Crypt::encryptString($rawValue)];
        }

        return ['value' => $rawValue, 'value_encrypted' => null];
    }

    /** Nilai asli, didekripsi otomatis kalau field ini sensitif. Dipakai di UI dashboard admin. */
    public function displayValue(): ?string
    {
        if ($this->value_encrypted !== null) {
            return Crypt::decryptString($this->value_encrypted);
        }

        return $this->value;
    }

    /** Versi aman untuk log/audit — nilai sensitif tidak pernah keluar, walau ke audit_logs. */
    public function redactedValue(): string
    {
        return $this->value_encrypted !== null ? '[REDACTED]' : (string) $this->value;
    }
}
