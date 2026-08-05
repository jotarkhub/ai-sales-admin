<?php

namespace App\Services\Lead;

use App\Enums\LeadStatus;
use App\Models\Business;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadFieldValue;
use App\Models\LeadFormSubmission;
use App\Models\LeadSource;
use App\Models\Product;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrasi lengkap alur "form masuk -> lead tercipta" (lihat docs/ARCHITECTURE.md #3).
 * Tidak pernah mengirim WhatsApp secara langsung — hanya menyiapkan FollowUp berstatus
 * pending yang akan dieksekusi oleh Fase 3 (WhatsApp Integration) setelah kredensial ada.
 */
class LeadIntakeService
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly AuditLogService $auditLog,
    ) {}

    public function process(array $data): LeadIntakeResult
    {
        $business = Business::query()->where('is_active', true)->firstOrFail();

        $existingSubmission = LeadFormSubmission::query()
            ->where('external_submission_id', $data['external_submission_id'])
            ->first();

        if ($existingSubmission) {
            if (! $existingSubmission->lead) {
                abort(409, 'Submission ini sudah pernah diterima tapi belum berhasil dibuatkan lead. Perlu investigasi manual.');
            }

            return new LeadIntakeResult($existingSubmission->lead, wasDuplicate: true, whatsappScheduled: false);
        }

        $phoneNumber = $this->phoneNormalizer->normalize($data['phone_number']);
        $sourceSlug = $data['source'] ?? LeadSource::GOOGLE_FORM;

        $leadSource = LeadSource::query()->firstOrCreate(
            ['slug' => $sourceSlug],
            ['name' => Str::headline($sourceSlug)]
        );

        $product = null;
        if (! empty($data['interested_product'])) {
            $product = Product::query()
                ->where('business_id', $business->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower($data['interested_product'])])
                ->first();
        }

        return DB::transaction(function () use ($business, $data, $phoneNumber, $leadSource, $product) {
            $submission = LeadFormSubmission::create([
                'business_id' => $business->id,
                'external_submission_id' => $data['external_submission_id'],
                'submitted_at' => $data['submitted_at'],
                'raw_payload' => $data['raw_answers'],
                'source' => $leadSource->slug,
                'consent_whatsapp' => (bool) $data['consent_whatsapp'],
                'processing_status' => 'pending',
            ]);

            $lead = Lead::create([
                'business_id' => $business->id,
                'lead_source_id' => $leadSource->id,
                'interested_product_id' => $product?->id,
                'external_submission_id' => $data['external_submission_id'],
                'name' => $data['name'],
                'phone_number' => $phoneNumber,
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'budget_estimate' => $data['budget_estimate'] ?? null,
                'purchase_timeline' => $data['purchase_timeline'] ?? null,
                'needs_notes' => $data['needs_notes'] ?? null,
                'consent_whatsapp' => (bool) $data['consent_whatsapp'],
                'status' => LeadStatus::New,
            ]);

            $submission->update([
                'lead_id' => $lead->id,
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            $customFieldsSaved = $this->storeCustomFieldValues($business, $lead, $data['custom_answers'] ?? []);

            LeadActivity::create([
                'business_id' => $business->id,
                'lead_id' => $lead->id,
                'type' => 'lead_created',
                'description' => 'Lead dibuat dari form submission ('.$leadSource->slug.').',
                'actor_type' => 'system',
            ]);

            $this->auditLog->recordSystem(
                action: 'lead.created',
                subject: $lead,
                // Sengaja TIDAK menyertakan custom_answers di sini — bisa berisi data sensitif
                // (NIK/KTP dkk.) yang tidak boleh mampir ke audit_logs walau di kolom "after".
                after: $lead->only(['status', 'phone_number', 'name', 'consent_whatsapp']),
            );

            if ($customFieldsSaved > 0) {
                LeadActivity::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'type' => 'custom_fields_saved',
                    'description' => $customFieldsSaved.' field custom tersimpan (lihat detail di halaman lead, bukan di log ini — field sensitif tidak ditampilkan mentah di aktivitas).',
                    'actor_type' => 'system',
                ]);
            }

            $whatsappScheduled = false;

            if ($lead->consent_whatsapp) {
                FollowUp::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'trigger_type' => 'form_submitted_initial_message',
                    'scheduled_at' => now(),
                    'status' => FollowUp::STATUS_PENDING,
                    'channel' => 'whatsapp',
                ]);

                LeadActivity::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'type' => 'initial_message_queued',
                    'description' => 'Pesan WhatsApp pertama dijadwalkan (menunggu integrasi WhatsApp Fase 3 — CREDENTIAL_REQUIRED). Belum benar-benar terkirim.',
                    'actor_type' => 'system',
                ]);

                $whatsappScheduled = true;
            } else {
                LeadActivity::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'type' => 'whatsapp_not_scheduled_no_consent',
                    'description' => 'Consent WhatsApp tidak diberikan — pesan otomatis tidak dijadwalkan.',
                    'actor_type' => 'system',
                ]);
            }

            return new LeadIntakeResult($lead, wasDuplicate: false, whatsappScheduled: $whatsappScheduled);
        });
    }

    /**
     * Simpan jawaban field custom (form builder). Field sensitif (is_sensitive) otomatis
     * dienkripsi lewat LeadFieldValue::makeFor() — lihat App\Models\LeadFieldValue.
     * Field yang tidak dikirim atau kosong dilewati (bukan error, kecuali wajib — sudah
     * divalidasi di LeadIntakeRequest sebelum service ini dipanggil).
     */
    private function storeCustomFieldValues(Business $business, Lead $lead, array $customAnswers): int
    {
        if (empty($customAnswers)) {
            return 0;
        }

        $definitions = $business->leadFieldDefinitions()->active()->get()->keyBy('key');
        $saved = 0;

        foreach ($customAnswers as $key => $rawValue) {
            $definition = $definitions->get($key);

            if (! $definition || $rawValue === null || $rawValue === '') {
                continue;
            }

            LeadFieldValue::create(array_merge(
                ['lead_id' => $lead->id, 'lead_field_definition_id' => $definition->id],
                LeadFieldValue::makeFor($definition, (string) $rawValue),
            ));

            $saved++;
        }

        return $saved;
    }
}
