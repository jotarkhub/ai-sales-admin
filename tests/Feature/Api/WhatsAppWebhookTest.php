<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'testing-app-secret-tidak-untuk-produksi'; // sama dengan .env.testing

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
    }

    private function postSigned(array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret ?? self::SECRET);

        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Hub-Signature-256' => 'sha256='.$signature,
        ]);

        return $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], $server, $body);
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
        $response = $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=echo-123');

        $response->assertOk();
        $response->assertSee('echo-123');
    }

    public function test_verify_handshake_dengan_token_salah_ditolak(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=echo-123');

        $response->assertForbidden();
    }

    public function test_receive_tanpa_signature_ditolak_401(): void
    {
        $body = json_encode($this->textMessagePayload('628123456789', 'wamid.NOAUTH', 'Halo'));

        $response = $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
        ]), $body);

        $response->assertUnauthorized();
    }

    public function test_receive_signature_salah_ditolak_401(): void
    {
        $response = $this->postSigned($this->textMessagePayload('628123456789', 'wamid.BADSIG', 'Halo'), secret: 'secret-salah');

        $response->assertUnauthorized();
    }

    public function test_receive_app_secret_belum_dikonfigurasi_menolak_503(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $response = $this->postSigned($this->textMessagePayload('628123456789', 'wamid.NOSECRET', 'Halo'));

        $response->assertStatus(503);
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
        $this->assertSame(LeadSource::WHATSAPP_INBOUND, $lead->leadSource->slug);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'lead_created_from_whatsapp_inbound',
        ]);
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
