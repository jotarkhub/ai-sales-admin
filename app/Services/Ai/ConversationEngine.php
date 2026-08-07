<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderContract;
use App\Contracts\WhatsAppProviderContract;
use App\Enums\ConversationStatus;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Message;
use App\Models\PromptVersion;
use App\Models\Ticket;
use App\Services\Audit\AuditLogService;
use Throwable;

/**
 * Orkestrasi "pesan masuk -> balasan AI keluar" sesuai docs/ARCHITECTURE.md sequence
 * "Percakapan WhatsApp". Dipanggil dari App\Services\WhatsApp\WhatsAppWebhookService
 * setelah pesan inbound tersimpan. TIDAK PERNAH membalas kalau conversation sedang
 * human_takeover — itu aturan keras, dicek ulang di sini (bukan cuma percaya caller).
 */
class ConversationEngine
{
    public function __construct(
        private readonly AiProviderContract $aiProvider,
        private readonly WhatsAppProviderContract $whatsAppProvider,
        private readonly ConversationContextBuilder $contextBuilder,
        private readonly ConversationGuardrailService $guardrail,
        private readonly AuditLogService $auditLog,
    ) {}

    public function respond(Conversation $conversation): void
    {
        try {
            $this->respondInternal($conversation);
        } catch (Throwable $e) {
            // Fallback aman kalau ada bug tak terduga di sini — jangan sampai gagal diam-diam
            // ATAU membuat webhook crash. Serahkan ke manusia daripada menebak.
            report($e);
            $conversation->update(['status' => ConversationStatus::HumanTakeover]);
        }
    }

    private function respondInternal(Conversation $conversation): void
    {
        $conversation->refresh();

        if ($conversation->status !== ConversationStatus::AiActive) {
            return;
        }

        $business = $conversation->business;
        $lead = $conversation->lead;

        $promptVersion = PromptVersion::where('business_id', $business->id)->where('is_active', true)->first();
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($business);
        $messages = $this->contextBuilder->buildMessages($conversation);

        $provider = config('services.ai.provider', 'fake') === 'openai' ? AiRun::PROVIDER_OPENAI : AiRun::PROVIDER_FAKE;
        $model = config('services.ai.model', 'gpt-4o-mini');

        $startedAt = microtime(true);
        $aiResult = $this->aiProvider->generateReply($messages, $systemPrompt);
        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        if (! $aiResult->success) {
            $aiRun = AiRun::create([
                'conversation_id' => $conversation->id,
                'prompt_version_id' => $promptVersion?->id,
                'provider' => $provider,
                'model_used' => $model,
                'latency_ms' => $latencyMs,
                'raw_output' => ['error' => $aiResult->errorMessage],
                'structured_output_valid' => false,
                'status' => AiRun::STATUS_FAILED,
            ]);

            $this->escalate($conversation, $lead, 'ai_provider_error', $aiResult->errorMessage ?? 'AI provider gagal tanpa pesan error.', $aiRun);

            return;
        }

        $parsed = AiStructuredReply::fromJson($aiResult->content);

        $aiRun = AiRun::create([
            'conversation_id' => $conversation->id,
            'prompt_version_id' => $promptVersion?->id,
            'provider' => $provider,
            'model_used' => $model,
            'input_tokens' => $aiResult->usage['prompt_tokens'] ?? null,
            'output_tokens' => $aiResult->usage['completion_tokens'] ?? null,
            'latency_ms' => $latencyMs,
            'raw_output' => ['content' => $aiResult->content, 'usage' => $aiResult->usage],
            'structured_output_valid' => $parsed !== null,
            'status' => $parsed !== null ? AiRun::STATUS_SUCCESS : AiRun::STATUS_GUARDRAIL_BLOCKED,
        ]);

        $guardrailResult = $this->guardrail->evaluate($parsed, $business);

        if ($guardrailResult->escalate) {
            if ($aiRun->status === AiRun::STATUS_SUCCESS) {
                $aiRun->update(['status' => AiRun::STATUS_GUARDRAIL_BLOCKED]);
            }

            $this->escalate($conversation, $lead, $guardrailResult->reason, $guardrailResult->detail, $aiRun, $parsed?->confidence);

            return;
        }

        $sendResult = $this->whatsAppProvider->sendTextMessage($business, $lead->phone_number, $parsed->replyMessage);

        if (! $sendResult->success) {
            // Balasan valid & lolos guardrail, tapi gagal terkirim (mis. gangguan jaringan
            // Meta) — dicatat sebagai gagal, BUKAN dianggap sudah dibalas. Tidak otomatis
            // eskalasi karena biasanya transient; admin bisa lihat riwayatnya di dashboard.
            LeadActivity::create([
                'business_id' => $conversation->business_id,
                'lead_id' => $lead->id,
                'type' => 'ai_reply_send_failed',
                'description' => "Balasan AI gagal dikirim: {$sendResult->errorMessage}",
                'actor_type' => 'system',
                'metadata' => ['error' => $sendResult->errorMessage],
            ]);

            $this->auditLog->recordSystem('ai_reply.send_failed', $conversation, after: ['error' => $sendResult->errorMessage]);

            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'sender_type' => 'ai',
            'ai_run_id' => $aiRun->id,
            'whatsapp_message_id' => $sendResult->providerMessageId,
            'body' => $parsed->replyMessage,
            'latest_status' => 'sent',
        ]);

        $aiRun->update(['message_id' => $message->id]);
        $conversation->update(['last_message_at' => now()]);

        LeadActivity::create([
            'business_id' => $conversation->business_id,
            'lead_id' => $lead->id,
            'type' => 'ai_reply_sent',
            'description' => 'AI membalas otomatis (intent: '.$parsed->intent.').',
            'actor_type' => 'ai',
        ]);

        $this->auditLog->record(
            action: 'ai_reply.sent',
            subject: $lead,
            after: ['message_id' => $message->id, 'confidence' => $parsed->confidence],
            actorType: AuditLog::ACTOR_AI,
        );
    }

    private function escalate(
        Conversation $conversation,
        Lead $lead,
        ?string $reason,
        ?string $detail,
        AiRun $aiRun,
        ?float $confidence = null,
    ): void {
        $escalation = Escalation::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'reason' => $reason ?: Escalation::REASON_LOW_CONFIDENCE,
            'reason_detail' => $detail,
            'status' => Escalation::STATUS_OPEN,
            'ai_confidence_at_escalation' => $confidence,
        ]);

        Ticket::create([
            'escalation_id' => $escalation->id,
            'lead_id' => $lead->id,
            'subject' => 'Eskalasi percakapan WhatsApp — '.($reason ?: 'perlu tinjauan admin'),
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $conversation->update(['status' => ConversationStatus::HumanTakeover]);

        LeadActivity::create([
            'business_id' => $conversation->business_id,
            'lead_id' => $lead->id,
            'type' => 'conversation_escalated',
            'description' => 'Percakapan dieskalasi ke admin: '.($detail ?: $reason),
            'actor_type' => 'ai',
        ]);

        $this->auditLog->record(
            action: 'escalation.created',
            subject: $escalation,
            after: $escalation->only(['reason', 'status', 'ai_confidence_at_escalation']),
            actorType: AuditLog::ACTOR_AI,
        );
    }
}
