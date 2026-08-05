<?php

namespace App\Services\WhatsApp;

use App\Enums\ConversationStatus;
use App\Enums\LeadStatus;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadSource;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\WebhookEvent;
use App\Services\Ai\ConversationEngine;
use App\Services\Audit\AuditLogService;
use App\Services\Lead\PhoneNumberNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Terima payload webhook WhatsApp Cloud API (sudah lolos verifikasi signature di
 * App\Http\Middleware\VerifyWhatsAppWebhookSignature) dan simpan sebagai data lokal.
 *
 * Setelah pesan inbound tersimpan (ingestion beres, transaksi commit), diteruskan ke
 * App\Services\Ai\ConversationEngine untuk memutuskan balasan AI — dipanggil DI LUAR
 * transaksi ingestion supaya kegagalan AI/pengiriman balasan tidak menandai pesan masuk
 * itu sendiri sebagai gagal diterima.
 */
class WhatsAppWebhookService
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly AuditLogService $auditLog,
        private readonly ConversationEngine $conversationEngine,
    ) {}

    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                // Field lain (mis. perubahan profil akun) di luar cakupan MVP — hanya pesan
                // & status pengiriman yang relevan untuk Conversation Engine.
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $messageItem) {
                    $this->handleInboundMessage($messageItem, $value);
                }

                foreach ($value['statuses'] ?? [] as $statusItem) {
                    $this->handleStatusUpdate($statusItem);
                }
            }
        }
    }

    private function handleInboundMessage(array $messageItem, array $value): void
    {
        $externalId = $messageItem['id'] ?? null;

        if (blank($externalId)) {
            return;
        }

        if (WebhookEvent::where('source', WebhookEvent::SOURCE_WHATSAPP)->where('external_event_id', $externalId)->exists()) {
            return; // Sudah pernah diterima -> no-op idempoten, mencegah pesan diproses dua kali.
        }

        try {
            $webhookEvent = WebhookEvent::create([
                'source' => WebhookEvent::SOURCE_WHATSAPP,
                'event_type' => 'message',
                'external_event_id' => $externalId,
                'signature_valid' => true,
                'payload' => $messageItem,
                'status' => WebhookEvent::STATUS_PENDING,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Race condition: dua request nyaris bersamaan lolos pengecekan exists() di atas.
            // Constraint unik(source, external_event_id) yang jadi penjaga terakhir.
            return;
        }

        $conversation = null;

        try {
            DB::transaction(function () use ($messageItem, $value, $externalId, $webhookEvent, &$conversation) {
                $business = Business::where('is_active', true)->firstOrFail();

                $rawFrom = $messageItem['from'] ?? null;

                if (blank($rawFrom)) {
                    throw new \RuntimeException('Field "from" tidak ada pada pesan masuk.');
                }

                $phoneNumber = $this->phoneNormalizer->normalize($rawFrom);

                $lead = Lead::where('business_id', $business->id)->where('phone_number', $phoneNumber)->first()
                    ?? $this->createLeadFromInboundMessage($business, $phoneNumber, $value);

                $conversation = $lead->conversations()->where('status', '!=', ConversationStatus::Closed->value)->first()
                    ?? Conversation::create([
                        'business_id' => $business->id,
                        'lead_id' => $lead->id,
                        'status' => ConversationStatus::AiActive,
                        'channel' => 'whatsapp',
                    ]);

                [$body, $mediaType] = $this->extractContent($messageItem);

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $lead->id,
                    'direction' => Message::DIRECTION_INBOUND,
                    'sender_type' => 'customer',
                    'whatsapp_message_id' => $externalId,
                    'body' => $body,
                    'media_type' => $mediaType,
                    'latest_status' => 'received',
                ]);

                $conversation->update(['last_message_at' => now()]);

                LeadActivity::create([
                    'business_id' => $business->id,
                    'lead_id' => $lead->id,
                    'type' => 'whatsapp_message_received',
                    'description' => $mediaType === null
                        ? 'Pesan WhatsApp masuk dari pelanggan.'
                        : "Pesan WhatsApp masuk dari pelanggan (tipe: {$mediaType}, isi tidak diproses otomatis).",
                    'actor_type' => 'system',
                ]);

                $this->auditLog->recordSystem(
                    action: 'whatsapp_message.received',
                    subject: $lead,
                    after: ['message_id' => $message->id, 'conversation_id' => $conversation->id],
                );

                $webhookEvent->update(['status' => WebhookEvent::STATUS_PROCESSED, 'processed_at' => now()]);
            });
        } catch (Throwable $e) {
            $webhookEvent->update(['status' => WebhookEvent::STATUS_FAILED, 'error_message' => $e->getMessage()]);

            return;
        }

        if ($conversation !== null) {
            $this->conversationEngine->respond($conversation);
        }
    }

    private function createLeadFromInboundMessage(Business $business, string $phoneNumber, array $value): Lead
    {
        $leadSource = LeadSource::firstOrCreate(
            ['slug' => LeadSource::WHATSAPP_INBOUND],
            ['name' => 'WhatsApp (Pesan Masuk)'],
        );

        $contactName = $value['contacts'][0]['profile']['name'] ?? null;

        $lead = Lead::create([
            'business_id' => $business->id,
            'lead_source_id' => $leadSource->id,
            'name' => filled($contactName) ? $contactName : 'Kontak WhatsApp '.$phoneNumber,
            'phone_number' => $phoneNumber,
            'consent_whatsapp' => true,
            'status' => LeadStatus::New,
        ]);

        LeadActivity::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'type' => 'lead_created_from_whatsapp_inbound',
            'description' => 'Lead dibuat otomatis karena pelanggan mengirim WhatsApp lebih dulu (belum pernah mengisi form).',
            'actor_type' => 'system',
        ]);

        $this->auditLog->recordSystem(
            action: 'lead.created',
            subject: $lead,
            after: $lead->only(['status', 'phone_number', 'name', 'consent_whatsapp']),
        );

        return $lead;
    }

    /** @return array{0: ?string, 1: ?string} [body, mediaType] */
    private function extractContent(array $messageItem): array
    {
        $type = $messageItem['type'] ?? 'unknown';

        if ($type === 'text') {
            return [$messageItem['text']['body'] ?? '', null];
        }

        // Media/tipe lain (image, audio, document, dst.) — sengaja TIDAK berpura-pura bisa
        // membaca isinya. Disimpan sebagai media_type saja; penanganan nyata (unduh/transkrip)
        // di luar cakupan MVP ini.
        return [null, $type];
    }

    private function handleStatusUpdate(array $statusItem): void
    {
        $messageId = $statusItem['id'] ?? null;
        $status = $statusItem['status'] ?? null;

        if (blank($messageId) || blank($status)) {
            return;
        }

        // Satu whatsapp_message_id bisa punya beberapa event status berbeda (sent, delivered,
        // read) — key idempotency gabungkan id + status supaya tiap tahap tetap tercatat sekali.
        $externalId = "{$messageId}:{$status}";

        if (WebhookEvent::where('source', WebhookEvent::SOURCE_WHATSAPP)->where('external_event_id', $externalId)->exists()) {
            return;
        }

        try {
            $webhookEvent = WebhookEvent::create([
                'source' => WebhookEvent::SOURCE_WHATSAPP,
                'event_type' => 'status',
                'external_event_id' => $externalId,
                'signature_valid' => true,
                'payload' => $statusItem,
                'status' => WebhookEvent::STATUS_PENDING,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            return;
        }

        try {
            $message = Message::where('whatsapp_message_id', $messageId)->first();

            if (! $message) {
                $webhookEvent->update([
                    'status' => WebhookEvent::STATUS_FAILED,
                    'error_message' => 'Message lokal tidak ditemukan untuk whatsapp_message_id ini.',
                ]);

                return;
            }

            MessageStatus::create([
                'message_id' => $message->id,
                'status' => $status,
                'raw_payload' => $statusItem,
                'occurred_at' => isset($statusItem['timestamp'])
                    ? Carbon::createFromTimestamp((int) $statusItem['timestamp'])
                    : now(),
            ]);

            $message->update(['latest_status' => $status]);

            $webhookEvent->update(['status' => WebhookEvent::STATUS_PROCESSED, 'processed_at' => now()]);
        } catch (Throwable $e) {
            $webhookEvent->update(['status' => WebhookEvent::STATUS_FAILED, 'error_message' => $e->getMessage()]);
        }
    }
}
