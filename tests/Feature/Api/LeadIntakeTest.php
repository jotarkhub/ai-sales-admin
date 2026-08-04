<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Lead;
use App\Models\LeadFormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LeadIntakeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'testing-secret-tidak-untuk-produksi'; // sama dengan .env.testing

    protected function setUp(): void
    {
        parent::setUp();

        Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    /**
     * Kirim POST dengan body JSON mentah + header X-Signature yang benar-benar dihitung
     * dari body itu (bukan lewat postJson(), supaya HMAC di middleware bisa cocok persis).
     */
    private function postSigned(string $uri, array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret ?? self::SECRET);

        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Signature' => 'sha256='.$signature,
        ]);

        return $this->call('POST', $uri, [], [], [], $server, $body);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'external_submission_id' => 'sub-'.bin2hex(random_bytes(6)),
            'submitted_at' => now()->toIso8601String(),
            'name' => 'Budi Pengujian',
            'phone_number' => '08123456789',
            'email' => 'budi@example.test',
            'interested_product' => null,
            'city' => 'Jakarta',
            'budget_estimate' => '1-3 juta',
            'purchase_timeline' => 'minggu ini',
            'needs_notes' => 'Butuh paket A',
            'source' => 'google_form',
            'consent_whatsapp' => true,
            'raw_answers' => ['Nama' => 'Budi Pengujian', 'Kota' => 'Jakarta'],
        ], $overrides);
    }

    public function test_submission_valid_membuat_lead_dan_menjadwalkan_pesan_whatsapp(): void
    {
        $payload = $this->validPayload();

        $response = $this->postSigned('/api/v1/leads/intake', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.duplicate', false);
        $response->assertJsonPath('data.whatsapp_scheduled', true);
        $response->assertJsonPath('data.status', 'new');

        $lead = Lead::where('external_submission_id', $payload['external_submission_id'])->first();
        $this->assertNotNull($lead);
        $this->assertSame('+628123456789', $lead->phone_number);
        $this->assertTrue($lead->consent_whatsapp);

        $this->assertDatabaseHas('lead_form_submissions', [
            'external_submission_id' => $payload['external_submission_id'],
            'processing_status' => 'processed',
        ]);

        $submission = $lead->formSubmissions()->first();
        // assertEquals (bukan assertSame): MySQL JSON column tidak menjamin urutan key sama
        // seperti saat disimpan (beda dengan SQLite yang menyimpan teks apa adanya).
        $this->assertEquals(['Nama' => 'Budi Pengujian', 'Kota' => 'Jakarta'], $submission->raw_payload);

        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'lead_created']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'initial_message_queued']);
        $this->assertDatabaseHas('follow_ups', [
            'lead_id' => $lead->id,
            'trigger_type' => 'form_submitted_initial_message',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.created',
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);
    }

    public function test_submission_duplikat_tidak_membuat_lead_kedua(): void
    {
        $payload = $this->validPayload();

        $first = $this->postSigned('/api/v1/leads/intake', $payload);
        $first->assertCreated();

        $second = $this->postSigned('/api/v1/leads/intake', $payload);
        $second->assertOk(); // 200, bukan 201 — no-op idempotent
        $second->assertJsonPath('data.duplicate', true);

        $this->assertSame(
            1,
            Lead::where('external_submission_id', $payload['external_submission_id'])->count()
        );
        $this->assertSame(
            1,
            LeadFormSubmission::where('external_submission_id', $payload['external_submission_id'])->count()
        );
    }

    public function test_nomor_whatsapp_tidak_valid_ditolak(): void
    {
        $payload = $this->validPayload(['phone_number' => 'bukan-nomor-telepon']);

        $response = $this->postSigned('/api/v1/leads/intake', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
        $this->assertDatabaseMissing('leads', ['external_submission_id' => $payload['external_submission_id']]);
    }

    public function test_consent_false_tidak_menjadwalkan_whatsapp(): void
    {
        $payload = $this->validPayload(['consent_whatsapp' => false]);

        $response = $this->postSigned('/api/v1/leads/intake', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.whatsapp_scheduled', false);

        $lead = Lead::where('external_submission_id', $payload['external_submission_id'])->firstOrFail();
        $this->assertDatabaseMissing('follow_ups', ['lead_id' => $lead->id]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp_not_scheduled_no_consent',
        ]);
    }

    public function test_signature_tidak_ada_ditolak_401(): void
    {
        $payload = $this->validPayload();
        $body = json_encode($payload);

        $response = $this->call('POST', '/api/v1/leads/intake', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
        ]), $body);

        $response->assertUnauthorized();
    }

    public function test_signature_salah_ditolak_401(): void
    {
        $payload = $this->validPayload();

        $response = $this->postSigned('/api/v1/leads/intake', $payload, secret: 'secret-yang-salah');

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('leads', ['external_submission_id' => $payload['external_submission_id']]);
    }

    public function test_secret_belum_dikonfigurasi_menolak_semua_request(): void
    {
        config(['services.lead_intake.secret' => null]);

        $payload = $this->validPayload();
        $response = $this->postSigned('/api/v1/leads/intake', $payload);

        $response->assertStatus(503);
    }
}
