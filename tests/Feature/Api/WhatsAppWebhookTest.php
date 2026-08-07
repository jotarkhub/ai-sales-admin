<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\IntegrationCredential;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 8c — webhook per bisnis (bukan satu URL global lagi). $this->business dibuat di setUp()
 * SUDAH punya App Secret + Verify Token sendiri di integration_credentials, supaya test yang
 * fokus ke ingestion pesan tidak perlu mengulang setup itu. Isolasi antar bisnis diuji terpisah
 * di test_*_bisnis_lain_*.
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'testing-app-secret-tidak-untuk-produksi';

    private const VERIFY_TOKEN = 'testing-verify-token-tidak-untuk-produksi';

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->giveWebhookCredentials($this->business);
    }

    private function giveWebhookCredentials(Business $business, string $appSecret = self::SECRET, string $verifyToken = self::VERIFY_TOKEN): void
    {
        IntegrationCredential::create([
            'business_id' => $business->id,
            'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
            'credential_key' => IntegrationCredential::WHATSAPP_KEY_APP_SECRET,
            'encrypted_value' => $appSecret,
            'is_active' => true,
        ]);

        IntegrationCredential::create([
            'business_id' => $business->id,
            'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
            'credential_key' => IntegrationCredential::WHATSAPP_KEY_VERIFY_TOKEN,
            'encrypted_value' => $verifyToken,
            'is_active' => true,
        ]);
    }

    private function webhookUrl(?Business $business = null): string
    {
        return '/api/v1/whatsapp/webhook/'.($business ?? $this->business)->webhook_slug;
    }

    private function postSigned(array $payload, ?string $secret = null, ?Business $business = null): TestResponse
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret ?? self::SECRET);

        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Hub-Signature-256' => 'sha256='.$signature,
        ]);

        return $this->call('POST', $this->webhookUrl($business), [], [], [], $server, $body);
    }

    private function textMessagePayload(string $from, string $id, string $body, ?string $contactName = null): array
    {
        $value = [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '628111222333', 'phone_number_id' => '1234567890'],
            'messages' => [[
                'from' => $from,
                'id' => $id,
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ];

        if ($contactName !== null) {
            $value['contacts'] = [['profile' => ['name' => $contactName], 'wa_id' => $from]];
        }

        return $this->wrapEntry($value);
    }

    private function mediaMessagePayload(string $from, string $id, string $type): array
    {
        return $this->wrapEntry([
            'messaging_product' => 'whatsapp',
            'messages' => [[
                'from' => $from,
                'id' => $id,
                'timestamp' => (string) now()->timestamp,
                'type' => $type,
                $type => ['id' => 'media-id-123'],
            ]],
        ]);
    }

    private function statusPayload(string $messageId, string $status): array
    {
        return $this->wrapEntry([
            'messaging_product' => 'whatsapp',
            'statuses' => [[
                'id' => $messageId,
                'status' => $status,
                'timestamp' => (string) now()->timestamp,
                'recipient_id' => '628123456789',
            ]],
        ]);
    }

    private function wrapEntry(array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID_TEST',
                'changes' => [['value' => $value, 'field' => 'messages']],
            ]],
        ];
    }

    public function test_verify_handshake_dengan_token_benar_mengembalikan_challenge(): void
    {
        $response = $this->get($this->webhookUrl().'?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=echo-123');

        $response->assertOk();
        $response->assertSee('echo-123');
    }

    public function test_verify_handshake_dengan_token_salah_ditolak(): void
    {
        $response = $this->get($this->webhookUrl().'?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=echo-123');

        $response->assertForbidden();
    }

    public function test_verify_handshake_bisnis_tidak_dikenal_404(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook/slug-tidak-ada?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=echo-123');

        $response->assertNotFound();
    }

    public function test_receive_tanpa_signature_ditolak_401(): void
    {
        $body = json_encode($this->textMessagePayload('628123456789', 'wamid.NOAUTH', 'Halo'));

        $response = $this->call('POST', $this->webhookUrl(), [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
        ]), $body);

        $response->assertUnauthorized();
    }

    public function test_receive_signature_salah_ditolak_401(): void
    {
        $response = $this->postSigned($this->textMessagePayload('628123456789', 'wamid.BADSIG', 'Halo'), secret: 'secret-salah');

        $response->assertUnauthorized();
    }

    public function test_receive_kredensial_webhook_belum_dikonfigurasi_menolak_503(): void
    {
        $business = Business::create(['name' => 'Bisnis Belum Setup (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        // Sengaja TIDAK memanggil giveWebhookCredentials() untuk bisnis ini.

        $response = $this->postSigned($this->textMessagePayload('628123456789', 'wamid.NOSECRET', 'Halo'), business: $business);

        $response->assertStatus(503);
    }

    public function test_kredensial_bisnis_lain_tidak_bisa_dipakai_verifikasi_signature_bisnis_ini(): void
    {
        // Isolasi multi-tenant (Fase 8c) — App Secret bisnis lain tidak boleh lolos verifikasi
        // untuk webhook bisnis ini, walau signature-nya secara matematis valid untuk secret itu.
        $otherBusiness = Business::create(['name' => 'Bisnis Lain (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $this->giveWebhookCredentials($otherBusiness, appSecret: 'secret-milik-bisnis-lain');

        $response = $this->postSigned(
            $this->textMessagePayload('628123456789', 'wamid.CROSSTENANT', 'Halo'),
            secret: 'secret-milik-bisnis-lain', // signature dihitung pakai secret bisnis LAIN
        ); // tapi dikirim ke URL webhook $this->business

        $response->assertUnauthorized();
    }

    public function test_pesan_teks_dari_lead_yang_sudah_ada_tersimpan(): void
    {
        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
        $lead = Lead::create([
            'business_id' => $this->business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'new',
        ]);

        $response = $this->postSigned($this->textMessagePayload('628123456789', 'wamid.EXISTING1', 'Ada promo apa?'));

        $response->assertOk();

        $this->assertDatabaseHas('messages', [
            'lead_id' => $lead->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'whatsapp_message_id' => 'wamid.EXISTING1',
            'body' => 'Ada promo apa?',
        ]);

        $this->assertDatabaseHas('conversations', ['lead_id' => $lead->id, 'status' => 'ai_active']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'whatsapp_message_received']);
        $this->assertDatabaseHas('webhook_events', [
            'external_event_id' => 'wamid.EXISTING1',
            'status' => WebhookEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_pesan_dari_nomor_baru_membuat_lead_otomatis(): void
    {
        $response = $this->postSigned($this->textMessagePayload('628199988877', 'wamid.NEWLEAD1', 'Halo, mau tanya-tanya', 'Siti Aminah'));

        $response->assertOk();

        $lead = Lead::where('phone_number', '+628199988877')->first();
        $this->assertNotNull($lead);
        $this->assertSame('Siti Aminah', $lead->name);
        $this->assertSame($this->business->id, $lead->business_id);
        $this->assertSame(LeadSource::WHATSAPP_INBOUND, $lead->leadSource->slug);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'lead_created_from_whatsapp_inbound',
        ]);
    }

    public function test_pesan_ke_webhook_bisnis_lain_masuk_ke_lead_bisnis_lain_bukan_bisnis_ini(): void
    {
        // Isolasi multi-tenant (Fase 8c) — bukti utama: nomor yang sama mengirim pesan ke DUA
        // webhook bisnis berbeda harus jadi DUA lead terpisah, masing-masing di bisnisnya sendiri.
        $otherBusiness = Business::create(['name' => 'Bisnis Lain (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $this->giveWebhookCredentials($otherBusiness, appSecret: 'secret-milik-bisnis-lain');

        $this->postSigned($this->textMessagePayload('628177778888', 'wamid.A1', 'Halo bisnis ini'))->assertOk();
        $this->postSigned(
            $this->textMessagePayload('628177778888', 'wamid.B1', 'Halo bisnis lain'),
            secret: 'secret-milik-bisnis-lain',
            business: $otherBusiness,
        )->assertOk();

        $this->assertSame(2, Lead::where('phone_number', '+628177778888')->count());
        $leadThisBusiness = Lead::where('phone_number', '+628177778888')->where('business_id', $this->business->id)->firstOrFail();
        $leadOtherBusiness = Lead::where('phone_number', '+628177778888')->where('business_id', $otherBusiness->id)->firstOrFail();

        $this->assertDatabaseHas('messages', ['lead_id' => $leadThisBusiness->id, 'whatsapp_message_id' => 'wamid.A1']);
        $this->assertDatabaseHas('messages', ['lead_id' => $leadOtherBusiness->id, 'whatsapp_message_id' => 'wamid.B1']);
    }

    public function test_pesan_duplikat_tidak_diproses_dua_kali(): void
    {
        $payload = $this->textMessagePayload('628123456789', 'wamid.DUP1', 'Halo');

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $this->assertSame(1, Message::where('whatsapp_message_id', 'wamid.DUP1')->count());
    }

    public function test_pesan_media_disimpan_tanpa_body_tapi_media_type_tercatat(): void
    {
        $response = $this->postSigned($this->mediaMessagePayload('628123456789', 'wamid.MEDIA1', 'image'));

        $response->assertOk();

        $this->assertDatabaseHas('messages', [
            'whatsapp_message_id' => 'wamid.MEDIA1',
            'media_type' => 'image',
            'body' => null,
        ]);
    }

    public function test_status_update_membuat_message_status_dan_update_latest_status(): void
    {
        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
        $lead = Lead::create([
            'business_id' => $this->business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'new',
        ]);
        $conversation = Conversation::create(['business_id' => $this->business->id, 'lead_id' => $lead->id, 'status' => 'ai_active']);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'direction' => 'outbound',
            'sender_type' => 'system',
            'whatsapp_message_id' => 'wamid.OUT1',
            'body' => 'Halo dari kami',
            'latest_status' => 'sent',
        ]);

        $response = $this->postSigned($this->statusPayload('wamid.OUT1', 'delivered'));

        $response->assertOk();

        $this->assertDatabaseHas('message_statuses', ['message_id' => $message->id, 'status' => 'delivered']);
        $this->assertSame('delivered', $message->fresh()->latest_status);
    }

    public function test_status_update_untuk_message_yang_tidak_dikenal_tidak_menyebabkan_500(): void
    {
        $response = $this->postSigned($this->statusPayload('wamid.TIDAK_ADA', 'delivered'));

        $response->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'external_event_id' => 'wamid.TIDAK_ADA:delivered',
            'status' => WebhookEvent::STATUS_FAILED,
        ]);
    }
}
