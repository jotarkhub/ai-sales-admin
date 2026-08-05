<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Enums\ConversationStatus;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Message;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Eksekusi follow_ups yang sudah jatuh tempo — mengirim WhatsApp lewat
 * App\Contracts\WhatsAppProviderContract (Fake selama testing/kredensial belum ada, Meta
 * begitu sudah CI_TEST_PASSED + kredensial tersedia). TIDAK pernah mengirim ke lead yang
 * tidak consent atau sudah opt-out — dicek ulang di sini, bukan cuma percaya status lama.
 *
 * Dipanggil dari App\Console\Commands\SendDueFollowUps (manual atau lewat scheduler).
 */
class FollowUpDispatchService
{
    private const RETRY_BACKOFF_MINUTES = 15;

    public function __construct(
        private readonly WhatsAppProviderContract $provider,
        private readonly MessageTemplateResolver $templateResolver,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @return array{sent: int, skipped: int, retry_scheduled: int, failed: int} */
    public function dispatchDue(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $result = ['sent' => 0, 'skipped' => 0, 'retry_scheduled' => 0, 'failed' => 0];

        $dueFollowUps = FollowUp::query()
            ->where('status', FollowUp::STATUS_PENDING)
            ->where('scheduled_at', '<=', $now)
            ->with(['business', 'lead'])
            ->get();

        foreach ($dueFollowUps as $followUp) {
            $outcome = $this->processOne($followUp);
            $result[$outcome]++;
        }

        return $result;
    }

    /** @return 'sent'|'skipped'|'retry_scheduled'|'failed' */
    private function processOne(FollowUp $followUp): string
    {
        $lead = $followUp->lead;
        $business = $followUp->business;

        if (! $lead || ! $business) {
            $followUp->update(['status' => FollowUp::STATUS_FAILED]);

            return 'failed';
        }

        if (! $lead->consent_whatsapp || $lead->isOptedOut()) {
            $this->skip($followUp, $lead, 'follow_up_skipped_opt_out', 'Lead tidak/tidak lagi consent WhatsApp — follow-up dibatalkan.');

            return 'skipped';
        }

        $body = $this->templateResolver->resolve($business, $followUp->trigger_type);

        if ($body === null) {
            $this->skip(
                $followUp,
                $lead,
                'follow_up_skipped_no_template',
                "Template pesan untuk trigger \"{$followUp->trigger_type}\" belum dikonfigurasi di Konfigurasi Bisnis."
            );

            return 'skipped';
        }

        $sendResult = $this->provider->sendTextMessage($lead->phone_number, $body);

        if ($sendResult->success) {
            $this->recordSent($followUp, $lead, $business, $body, $sendResult->providerMessageId);

            return 'sent';
        }

        return $this->recordFailure($followUp, $lead, $sendResult->errorMessage ?? 'Tidak diketahui');
    }

    private function skip(FollowUp $followUp, Lead $lead, string $activityType, string $description): void
    {
        $followUp->update(['status' => FollowUp::STATUS_SKIPPED]);

        LeadActivity::create([
            'business_id' => $followUp->business_id,
            'lead_id' => $lead->id,
            'type' => $activityType,
            'description' => $description,
            'actor_type' => 'system',
        ]);

        $this->auditLog->recordSystem(action: 'follow_up.skipped', subject: $followUp, after: ['status' => FollowUp::STATUS_SKIPPED, 'reason' => $activityType]);
    }

    private function recordSent(FollowUp $followUp, Lead $lead, Business $business, string $body, ?string $providerMessageId): void
    {
        DB::transaction(function () use ($followUp, $lead, $business, $body, $providerMessageId) {
            $conversation = $lead->conversations()->where('status', '!=', ConversationStatus::Closed->value)->first()
                ?? Conversation::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'status' => ConversationStatus::AiActive,
                    'channel' => 'whatsapp',
                ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'lead_id' => $lead->id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'sender_type' => 'system',
                'whatsapp_message_id' => $providerMessageId,
                'body' => $body,
                'latest_status' => 'sent',
            ]);

            $conversation->update(['last_message_at' => now()]);

            $followUp->update(['status' => FollowUp::STATUS_SENT, 'sent_message_id' => $message->id]);

            LeadActivity::create([
                'business_id' => $business->id,
                'lead_id' => $lead->id,
                'type' => 'whatsapp_message_sent',
                'description' => 'Pesan WhatsApp follow-up ('.$followUp->trigger_type.') berhasil dikirim.',
                'actor_type' => 'system',
            ]);

            $this->auditLog->recordSystem(
                action: 'follow_up.sent',
                subject: $followUp,
                after: ['status' => FollowUp::STATUS_SENT, 'message_id' => $message->id],
            );
        });
    }

    /** @return 'retry_scheduled'|'failed' */
    private function recordFailure(FollowUp $followUp, Lead $lead, string $errorMessage): string
    {
        $nextAttempt = $followUp->attempt_number + 1;

        if ($nextAttempt > $followUp->max_attempts) {
            $followUp->update(['status' => FollowUp::STATUS_FAILED, 'attempt_number' => $nextAttempt]);

            LeadActivity::create([
                'business_id' => $followUp->business_id,
                'lead_id' => $lead->id,
                'type' => 'whatsapp_message_failed_permanently',
                'description' => "Follow-up gagal dikirim setelah {$followUp->max_attempts} percobaan: {$errorMessage}",
                'actor_type' => 'system',
                'metadata' => ['error' => $errorMessage],
            ]);

            $this->auditLog->recordSystem(action: 'follow_up.failed', subject: $followUp, after: ['status' => FollowUp::STATUS_FAILED, 'error' => $errorMessage]);

            return 'failed';
        }

        $followUp->update([
            'attempt_number' => $nextAttempt,
            'scheduled_at' => now()->addMinutes(self::RETRY_BACKOFF_MINUTES),
        ]);

        LeadActivity::create([
            'business_id' => $followUp->business_id,
            'lead_id' => $lead->id,
            'type' => 'whatsapp_message_failed_retry_scheduled',
            'description' => "Percobaan ke-{$followUp->attempt_number} gagal ({$errorMessage}), dijadwalkan ulang.",
            'actor_type' => 'system',
            'metadata' => ['error' => $errorMessage],
        ]);

        return 'retry_scheduled';
    }
}
