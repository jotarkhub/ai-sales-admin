<?php

namespace Tests\Feature\EndToEnd;

use App\Contracts\AiProviderContract;
use App\Models\AiRun;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\FollowUp;
use App\Models\IntegrationCredential;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\WhatsApp\FollowUpDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifikasi end-to-end (Fase 7) — bukan test unit per modul (itu sudah ada di masing-masing
 * tests/Feature/*), tapi memastikan MODUL-MODUL YANG SUDAH LULUS CI SENDIRI-SENDIRI benar-benar
 * nyambung kalau dipakai berurutan seperti alur nyata: form masuk -> follow-up terkirim ->
 * pelanggan membalas -> AI merespons -> admin mengelola dari dashboard.
 *
 * PENTING (lihat docs/STATUS.md "Provider Fake — Aturan Keras"): ini pakai FakeWhatsAppProvider
 * & FakeAiProvider (default binding saat APP_ENV=testing). Lulus test ini TIDAK berarti
 * integrasi Meta/OpenAI sungguhan sudah terverifikasi — itu tetap CREDENTIAL_REQUIRED sampai
 * token asli tersedia dan alur yang sama diulang di lingkungan staging/production.
 */
class FullPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const LEAD_INTAKE_SECRET = 'testing-secret-tidak-untuk-produksi';

    private const WEBHOOK_SECRET = 'testing-app-secret-tidak-untuk-produksi';

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'message_templates' => ['auto_reply_awal' => 'Halo! Terima kasih sudah mendaftar, ada yang bisa kami bantu?'],
        ]);

        // Fase 8c — webhook per bisnis, jadi test end-to-end butuh kredensial webhook bisnis
        // ini sendiri (dulu cukup WHATSAPP_APP_SECRET global di .env.testing).
        IntegrationCredential::create([
            'business_id' => $this->business->id,
            'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
            'credential_key' => IntegrationCredential::WHATSAPP_KEY_APP_SECRET,
            'encrypted_value' => self::WEBHOOK_SECRET,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $this->business->id]);
        $user->roles()->attach($role);

        return $user;
    }

    private function postLeadIntake(array $overrides = []): TestResponse
    {
        $payload = array_merge([
            'external_submission_id' => 'e2e-sub-1',
            'submitted_at' => now()->toIso8601String(),
            'name' => 'Budi Pengujian',
            'phone_number' => '08123456789',
            'email' => 'budi@example.test',
            'interested_product' => null,
            'city' => 'Jakarta',
            'budget_estimate' => '1-3 juta',
            'purchase_timeline' => 'minggu ini',
            'needs_notes' => 'Butuh info paket',
            'source' => 'google_form',
            'consent_whatsapp' => true,
            'raw_answers' => ['Nama' => 'Budi Pengujian'],
        ], $overrides);

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, self::LEAD_INTAKE_SECRET);

        return $this->call('POST', '/api/v1/leads/intake', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Signature' => 'sha256='.$signature,
        ]), $body);
    }

    private function postWebhookInboundText(string $from, string $messageId, string $text): TestResponse
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_E2E',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/v1/whatsapp/webhook/'.$this->business->webhook_slug, [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Hub-Signature-256' => 'sha256='.$signature,
        ]), $body);
    }

    public function test_alur_lengkap_form_masuk_sampai_ai_membalas_dan_admin_kelola_dashboard(): void
    {
        // 1) Form masuk lewat Lead Intake -> lead + follow-up pending tercipta.
        $intakeResponse = $this->postLeadIntake();
        $intakeResponse->assertCreated();

        $lead = Lead::where('external_submission_id', 'e2e-sub-1')->firstOrFail();
        $this->assertSame('+628123456789', $lead->phone_number);
        $this->assertDatabaseHas('follow_ups', [
            'lead_id' => $lead->id,
            'trigger_type' => 'form_submitted_initial_message',
            'status' => FollowUp::STATUS_PENDING,
        ]);

        // 2) FollowUp Dispatch mengirim pesan pembuka (via FakeWhatsAppProvider).
        $dispatchResult = app(FollowUpDispatchService::class)->dispatchDue();
        $this->assertSame(1, $dispatchResult['sent']);

        $conversation = Conversation::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('ai_active', $conversation->status->value);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'system',
            'body' => 'Halo! Terima kasih sudah mendaftar, ada yang bisa kami bantu?',
        ]);

        // 3) Pelanggan membalas lewat WhatsApp -> webhook simpan pesan masuk DI conversation
        //    yang sama (bukan bikin lead/conversation baru untuk nomor yang sama).
        $replyResponse = $this->postWebhookInboundText('628123456789', 'wamid.E2E-CUSTOMER-1', 'Halo, saya mau tanya harganya berapa?');
        $replyResponse->assertOk();

        $this->assertSame(1, Lead::where('phone_number', '+628123456789')->count());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'whatsapp_message_id' => 'wamid.E2E-CUSTOMER-1',
            'body' => 'Halo, saya mau tanya harganya berapa?',
        ]);

        // 4) Conversation Engine otomatis terpicu dari webhook -> AI membalas (FakeAiProvider
        //    default: JSON valid, escalation_required=false) & tercatat sebagai ai_run.
        $conversation->refresh();
        $this->assertSame('ai_active', $conversation->status->value);
        $this->assertSame(3, Message::where('conversation_id', $conversation->id)->count()); // welcome + customer + AI

        $aiMessage = Message::where('conversation_id', $conversation->id)->where('sender_type', 'ai')->firstOrFail();
        $this->assertNotNull($aiMessage->ai_run_id);
        $this->assertDatabaseHas('ai_runs', ['id' => $aiMessage->ai_run_id, 'status' => AiRun::STATUS_SUCCESS]);

        // 5) Delivery receipt untuk balasan AI diterima lewat webhook status update.
        $statusPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_E2E',
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['statuses' => [[
                        'id' => $aiMessage->whatsapp_message_id,
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                    ]]],
                ]],
            ]],
        ];
        $statusBody = json_encode($statusPayload);
        $statusSignature = hash_hmac('sha256', $statusBody, self::WEBHOOK_SECRET);
        $this->call('POST', '/api/v1/whatsapp/webhook/'.$this->business->webhook_slug, [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'X-Hub-Signature-256' => 'sha256='.$statusSignature,
        ]), $statusBody)->assertOk();

        $this->assertSame('delivered', $aiMessage->fresh()->latest_status);

        // 6) Admin login & lihat semuanya dari dashboard.
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('leads.show', $lead))->assertOk()->assertSee('Budi Pengujian');
        $this->actingAs($admin)->get(route('conversations.show', $conversation))->assertOk();

        // 7) Admin ambil alih percakapan secara manual.
        $this->actingAs($admin)->post(route('conversations.takeover', $conversation))->assertRedirect();
        $this->assertSame('human_takeover', $conversation->fresh()->status->value);

        // 8) Pelanggan kirim pesan lagi SELAGI human_takeover -> tersimpan, tapi AI TIDAK
        //    membalas (aturan keras Fase 4b, diverifikasi lagi di titik integrasi ini).
        $countBefore = Message::where('conversation_id', $conversation->id)->count();
        $aiRunCountBefore = AiRun::where('conversation_id', $conversation->id)->count();

        $this->postWebhookInboundText('628123456789', 'wamid.E2E-CUSTOMER-2', 'Halo, masih di sana?')->assertOk();

        // Cuma pesan pelanggan yang nambah — tidak ada balasan AI baru & tidak ada ai_run baru.
        $this->assertSame($countBefore + 1, Message::where('conversation_id', $conversation->id)->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('sender_type', 'ai')->count());
        $this->assertSame($aiRunCountBefore, AiRun::where('conversation_id', $conversation->id)->count());

        // 9) Admin kembalikan ke AI, lalu konfirmasi lead won -> percakapan otomatis ditutup.
        $this->actingAs($admin)->post(route('conversations.release', $conversation))->assertRedirect();
        $this->assertSame('ai_active', $conversation->fresh()->status->value);

        $this->actingAs($admin)->post(route('leads.confirm-won', $lead))->assertRedirect();
        $this->assertSame('won', $lead->fresh()->status->value);
        $this->assertSame('closed', $conversation->fresh()->status->value);
    }

    public function test_ai_diseskalasi_saat_komplain_dan_admin_bisa_lihat_tiket_di_dashboard(): void
    {
        $this->postLeadIntake(['external_submission_id' => 'e2e-sub-2', 'phone_number' => '08199988877'])->assertCreated();
        app(FollowUpDispatchService::class)->dispatchDue();

        $lead = Lead::where('phone_number', '+628199988877')->firstOrFail();
        $conversation = Conversation::where('lead_id', $lead->id)->firstOrFail();

        app(AiProviderContract::class)->respondWith(json_encode([
            'intent' => 'komplain',
            'reply_message' => 'Baik, saya mengerti.',
            'escalation_required' => true,
            'escalation_reason' => 'customer_requested_human',
            'confidence' => 0.5,
        ]));

        $this->postWebhookInboundText('628199988877', 'wamid.E2E-COMPLAINT-1', 'Saya mau bicara dengan orang asli, bukan bot!')->assertOk();

        $this->assertSame('human_takeover', $conversation->fresh()->status->value);
        $this->assertDatabaseHas('escalations', ['conversation_id' => $conversation->id, 'status' => Escalation::STATUS_OPEN]);
        $this->assertDatabaseHas('tickets', ['lead_id' => $lead->id, 'status' => 'open']);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('conversations.show', $conversation))->assertOk();
    }
}
