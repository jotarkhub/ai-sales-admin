<?php

namespace App\Services\WhatsApp;

use App\Models\Business;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;

/**
 * Simpan & baca status kredensial WhatsApp per bisnis (Fase 8b) — dipakai
 * App\Http\Controllers\PlatformBusinessController, bukan dipanggil dari alur kirim pesan
 * (itu tugas WhatsAppCredentialResolver, read-only & tanpa audit log).
 *
 * Nilai asli TIDAK PERNAH ditulis ke audit_logs (aturan keras "Nilai field sensitif tidak
 * pernah ditulis ke audit_logs", lihat docs/ARCHITECTURE.md) — hanya field mana yang berubah.
 * View juga TIDAK PERNAH menerima nilai asli, cuma status "terisi/belum" dari status().
 */
class WhatsAppCredentialManager
{
    private const KEYS = [
        IntegrationCredential::WHATSAPP_KEY_TOKEN,
        IntegrationCredential::WHATSAPP_KEY_PHONE_NUMBER_ID,
        IntegrationCredential::WHATSAPP_KEY_APP_SECRET,
        IntegrationCredential::WHATSAPP_KEY_VERIFY_TOKEN,
    ];

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, string|null>  $values  Keyed by IntegrationCredential::WHATSAPP_KEY_*.
     *                                              Field kosong/null DILEWATI (tidak menimpa nilai
     *                                              tersimpan) supaya admin bisa update satu field
     *                                              saja tanpa harus tahu/menempel ulang field lain.
     */
    public function save(Business $business, array $values, User $actor): void
    {
        $changedKeys = [];

        foreach (self::KEYS as $key) {
            $value = $values[$key] ?? null;

            if (blank($value)) {
                continue;
            }

            IntegrationCredential::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
                    'credential_key' => $key,
                ],
                [
                    'encrypted_value' => $value,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]
            );

            $changedKeys[] = $key;
        }

        if ($changedKeys === []) {
            return;
        }

        $this->auditLog->record(
            action: 'integration_credential.whatsapp_updated',
            subject: $business,
            after: ['fields_updated' => $changedKeys],
            actor: $actor,
            actorType: 'user',
        );
    }

    /** @return array<string, array{configured: bool, updated_at: ?Carbon}> */
    public function status(Business $business): array
    {
        $rows = IntegrationCredential::query()
            ->where('business_id', $business->id)
            ->where('provider', IntegrationCredential::PROVIDER_WHATSAPP)
            ->get()
            ->keyBy('credential_key');

        $status = [];

        foreach (self::KEYS as $key) {
            $row = $rows->get($key);

            $status[$key] = [
                'configured' => $row !== null && $row->is_active && filled($row->encrypted_value),
                'updated_at' => $row?->updated_at,
            ];
        }

        return $status;
    }
}
