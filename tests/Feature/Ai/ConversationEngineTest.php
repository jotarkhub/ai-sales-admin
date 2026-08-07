<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiProviderContract;
use App\Contracts\WhatsAppProviderContract;
use App\Models\AiRun;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Message;
use App\Services\Ai\AiReplyResult;
use App\Services\Ai\ConversationEngine;
use App\Services\Ai\FakeAiProvider;
use App\Services\WhatsApp\WhatsAppSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationEngineTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Lead $lead;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);

        $this->lead = Lead::create([
            'business_id' => $this->business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'new',
        ]);

        $this->conversation = Conversation::create([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'status' => 'ai_active',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'lead_id' => $this->lead->id,
            'direction' => Message::DIRECTION_INBOUND,
            'sender_type' => 'customer',
            'body' => 'Halo, ada promo apa saja?',
            'latest_status' => 'received',
        ]);
    }

    private function fakeAi(): FakeAiProvider
    {
        return app(AiProviderContract::class);
    }

    private function bindFailingAiProvider(string $error = 'Simulasi AI down.'): void
    {
        $fake = new class($error) implements AiProviderContract
        {
            public function __construct(private readonly string $error) {}

            public function generateReply(array $messages, ?string $systemPrompt = null): AiReplyResult
            {
                return AiReplyResult::failure($this->error);
            }
        };

        $this->app->instance(AiProviderContract::class, $fake);
    }

    private function bindFailingWhatsAppProvider(string $error = 'Simulasi gagal kirim.'): void
    {
        $fake = new class($error) implements WhatsAppProviderContract
        {
            public function __construct(private readonly string $error) {}

            public function sendTextMessage(Business $business, string $to, string $body): WhatsAppSendResult
            {
                return WhatsAppSendResult::failure($this->error);
            }
        };

        $this->app->instance(WhatsAppProviderContract::class, $fake);
    }

    public function test_balasan_valid_terkirim_dan_tercatat_lengkap(): void
    {
        $this->fakeAi()->respondWith(json_encode([
            'intent' => 'tanya_promo',
            'reply_message' => 'Halo! Saat ini ada promo diskon 10% untuk paket A.',
            'escalation_required' => false,
            'escalation_reason' => null,
            'confidence' => 0.92,
        ]));

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'body' => 'Halo! Saat ini ada promo diskon 10% untuk paket A.',
        ]);

        $this->assertDatabaseHas('ai_runs', [
            'conversation_id' => $this->conversation->id,
            'provider' => 'fake',
            'status' => AiRun::STATUS_SUCCESS,
            'structured_output_valid' => 1,
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'ai_reply_sent',
        ]);

        $this->assertSame('ai_active', $this->conversation->fresh()->status->value);
    }

    public function test_escalation_required_membuat_eskalasi_dan_tiket_serta_human_takeover(): void
    {
        $this->fakeAi()->respondWith(json_encode([
            'intent' => 'komplain',
            'reply_message' => 'Baik, saya mengerti kekesalan Anda.',
            'escalation_required' => true,
            'escalation_reason' => 'Pelanggan komplain dan minta bicara manusia.',
            'confidence' => 0.4,
        ]));

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertSame('human_takeover', $this->conversation->fresh()->status->value);

        $this->assertDatabaseHas('escalations', [
            'conversation_id' => $this->conversation->id,
            'lead_id' => $this->lead->id,
            'status' => Escalation::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('tickets', ['lead_id' => $this->lead->id, 'status' => 'open']);

        $this->assertDatabaseMissing('messages', ['conversation_id' => $this->conversation->id, 'sender_type' => 'ai']);
    }

    public function test_confidence_di_bawah_ambang_bisnis_diseskalasi(): void
    {
        $this->business->update(['ai_authority_limit' => ['min_confidence' => 0.9]]);

        $this->fakeAi()->respondWith(json_encode([
            'intent' => 'tanya_harga',
            'reply_message' => 'Harganya sekitar segitu ya, saya kurang yakin.',
            'escalation_required' => false,
            'escalation_reason' => null,
            'confidence' => 0.3,
        ]));

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertSame('human_takeover', $this->conversation->fresh()->status->value);
        $this->assertDatabaseHas('escalations', ['conversation_id' => $this->conversation->id, 'reason' => Escalation::REASON_LOW_CONFIDENCE]);
    }

    public function test_output_ai_bukan_json_valid_diseskalasi(): void
    {
        $this->fakeAi()->respondWith('ini bukan json sama sekali');

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertSame('human_takeover', $this->conversation->fresh()->status->value);

        $this->assertDatabaseHas('ai_runs', [
            'conversation_id' => $this->conversation->id,
            'structured_output_valid' => 0,
            'status' => AiRun::STATUS_GUARDRAIL_BLOCKED,
        ]);

        $this->assertDatabaseHas('escalations', ['conversation_id' => $this->conversation->id]);
    }

    public function test_ai_provider_gagal_diseskalasi_dengan_ai_run_status_failed(): void
    {
        $this->bindFailingAiProvider('Timeout ke OpenAI.');

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertSame('human_takeover', $this->conversation->fresh()->status->value);

        $this->assertDatabaseHas('ai_runs', [
            'conversation_id' => $this->conversation->id,
            'status' => AiRun::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('escalations', ['conversation_id' => $this->conversation->id, 'reason' => 'ai_provider_error']);
    }

    public function test_conversation_human_takeover_tidak_memicu_ai_sama_sekali(): void
    {
        $this->conversation->update(['status' => 'human_takeover']);

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertDatabaseCount('ai_runs', 0);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $this->conversation->id, 'sender_type' => 'ai']);
    }

    public function test_balasan_lolos_guardrail_tapi_gagal_dikirim_tidak_membuat_message_dan_tidak_eskalasi(): void
    {
        $this->fakeAi()->respondWith(json_encode([
            'intent' => 'tanya_promo',
            'reply_message' => 'Ini balasan yang seharusnya terkirim.',
            'escalation_required' => false,
            'escalation_reason' => null,
            'confidence' => 0.95,
        ]));
        $this->bindFailingWhatsAppProvider('Rate limit Meta.');

        app(ConversationEngine::class)->respond($this->conversation);

        $this->assertDatabaseMissing('messages', ['conversation_id' => $this->conversation->id, 'sender_type' => 'ai']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->lead->id, 'type' => 'ai_reply_send_failed']);
        $this->assertSame('ai_active', $this->conversation->fresh()->status->value);
    }
}
