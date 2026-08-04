<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\KnowledgeItemStatus;
use App\Enums\LeadStatus;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\FollowUp;
use App\Models\IntegrationCredential;
use App\Models\KnowledgeItem;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadFormSubmission;
use App\Models\LeadScore;
use App\Models\LeadScoreComponent;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\Product;
use App\Models\PromptVersion;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test ini BUKAN test unit sempit — sengaja menembus seluruh 24 tabel inti dalam satu alur,
 * supaya migration + model + relationship yang ditulis tanpa eksekusi lokal (lihat
 * docs/STATUS.md) segera ketahuan kalau ada nama kolom/relasi yang salah, sebelum modul
 * Fase 2 berikutnya dibangun di atasnya.
 */
class CoreSchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seluruh_tabel_inti_bisa_dibuat_dan_direlasikan(): void
    {
        $business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'assistant_name' => 'Nadia',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);

        $admin = User::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach($adminRole);

        $this->assertTrue($admin->fresh()->hasRole(Role::ADMIN));
        $this->assertTrue($admin->isAdmin());

        $leadSource = LeadSource::create(['name' => 'Google Form', 'slug' => LeadSource::GOOGLE_FORM]);

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Paket Uji Coba',
            'price' => 1500000,
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'business_id' => $business->id,
            'lead_source_id' => $leadSource->id,
            'interested_product_id' => $product->id,
            'external_submission_id' => 'test-submission-001',
            'name' => 'Budi Pengujian',
            'phone_number' => '+6281234567890',
            'city' => 'Jakarta',
            'consent_whatsapp' => true,
            'status' => LeadStatus::New,
        ]);

        $this->assertSame(LeadStatus::New, $lead->fresh()->status);
        $this->assertFalse($lead->isOptedOut());

        $submission = LeadFormSubmission::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'external_submission_id' => 'test-submission-001',
            'submitted_at' => now(),
            'raw_payload' => ['nama' => 'Budi Pengujian', 'produk' => 'Paket Uji Coba'],
            'source' => 'google_form',
            'consent_whatsapp' => true,
            'processing_status' => 'processed',
        ]);
        $this->assertIsArray($submission->fresh()->raw_payload);

        $conversation = Conversation::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'channel' => 'whatsapp',
            'status' => ConversationStatus::AiActive,
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'sender_type' => 'ai',
            'body' => 'Halo Budi, terima kasih sudah mengisi form kami.',
            'latest_status' => 'sent',
        ]);

        MessageStatus::create([
            'message_id' => $message->id,
            'status' => 'delivered',
            'occurred_at' => now(),
        ]);

        LeadActivity::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'type' => 'lead_created',
            'actor_type' => 'system',
        ]);

        $leadScore = LeadScore::create([
            'lead_id' => $lead->id,
            'total_score' => 42,
            'computed_by' => 'system',
            'computed_at' => now(),
        ]);

        LeadScoreComponent::create([
            'lead_score_id' => $leadScore->id,
            'component_key' => 'urgency',
            'label' => 'Urgensi Pembelian',
            'weight' => 1.5,
            'raw_value' => 10,
            'points' => 15,
        ]);

        $tag = Tag::create(['business_id' => $business->id, 'name' => 'Hot Lead', 'slug' => 'hot-lead']);
        $lead->tags()->attach($tag->id, ['tagged_by' => $admin->id, 'tagged_at' => now()]);
        $this->assertTrue($lead->tags()->where('tags.id', $tag->id)->exists());

        $knowledge = KnowledgeItem::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'category' => 'faq',
            'title' => 'Cara pembayaran',
            'content' => 'Transfer bank atau QRIS.',
            'status' => KnowledgeItemStatus::Published,
        ]);
        $this->assertTrue(KnowledgeItem::usableByAi()->whereKey($knowledge->id)->exists());

        $draftKnowledge = KnowledgeItem::create([
            'business_id' => $business->id,
            'category' => 'faq',
            'title' => 'Draft belum terbit',
            'content' => 'Belum boleh dipakai AI.',
            'status' => KnowledgeItemStatus::Draft,
        ]);
        $this->assertFalse(KnowledgeItem::usableByAi()->whereKey($draftKnowledge->id)->exists());

        FollowUp::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'sent_message_id' => $message->id,
            'trigger_type' => 'first_message_no_reply',
            'scheduled_at' => now()->addDay(),
            'status' => FollowUp::STATUS_PENDING,
        ]);

        $promptVersion = PromptVersion::create([
            'business_id' => $business->id,
            'name' => 'System Prompt Sales Admin',
            'version_label' => 'v1',
            'content' => 'Kamu adalah asisten virtual sales...',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $aiRun = AiRun::create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'prompt_version_id' => $promptVersion->id,
            'provider' => AiRun::PROVIDER_FAKE,
            'model_used' => 'fake-model-for-testing',
            'input_tokens' => 120,
            'output_tokens' => 40,
            'estimated_cost_usd' => 0.000123,
            'structured_output_valid' => true,
            'status' => AiRun::STATUS_SUCCESS,
        ]);
        $message->update(['ai_run_id' => $aiRun->id]);
        $this->assertSame($aiRun->id, $message->fresh()->ai_run_id);

        $escalation = Escalation::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'reason' => Escalation::REASON_CUSTOMER_REQUESTED_HUMAN,
            'status' => Escalation::STATUS_OPEN,
        ]);

        $ticket = Ticket::create([
            'escalation_id' => $escalation->id,
            'lead_id' => $lead->id,
            'subject' => 'Customer minta bicara dengan admin',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $this->assertSame($ticket->id, $escalation->fresh()->ticket->id);

        WebhookEvent::create([
            'source' => WebhookEvent::SOURCE_WHATSAPP,
            'event_type' => 'message',
            'external_event_id' => 'wh-evt-001',
            'signature_valid' => true,
            'payload' => ['test' => true],
            'status' => WebhookEvent::STATUS_PROCESSED,
            'received_at' => now(),
        ]);

        // Idempotency: external_event_id + source harus unik.
        $this->assertDatabaseCount('webhook_events', 1);

        $credential = IntegrationCredential::create([
            'business_id' => $business->id,
            'provider' => IntegrationCredential::PROVIDER_OPENAI,
            'credential_key' => 'api_key',
            'encrypted_value' => 'nilai-rahasia-hanya-untuk-test',
            'is_active' => true,
        ]);
        $this->assertSame('nilai-rahasia-hanya-untuk-test', $credential->fresh()->encrypted_value);
        $rawColumnValue = \Illuminate\Support\Facades\DB::table('integration_credentials')
            ->where('id', $credential->id)->value('encrypted_value');
        $this->assertNotSame('nilai-rahasia-hanya-untuk-test', $rawColumnValue, 'Nilai di kolom DB harus terenkripsi, bukan plaintext.');

        AuditLog::create([
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'action' => 'lead.created',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'after' => ['status' => 'new'],
        ]);

        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('escalations', 1);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        // Traversal relasi lintas tabel, memastikan nama relasi & foreign key konsisten.
        $this->assertSame($business->id, $lead->fresh()->business->id);
        $this->assertSame($lead->id, $conversation->fresh()->lead->id);
        $this->assertSame($conversation->id, $message->fresh()->conversation->id);
        $this->assertCount(1, $conversation->fresh()->messages);
        $this->assertCount(1, $lead->fresh()->conversations);
        $this->assertCount(1, $lead->fresh()->activities);
        $this->assertCount(1, $lead->fresh()->scores);
        $this->assertCount(1, $leadScore->fresh()->components);
    }
}
