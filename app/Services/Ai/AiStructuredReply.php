<?php

namespace App\Services\Ai;

/**
 * Bentuk terstruktur yang WAJIB dikembalikan AI (dipaksa lewat system prompt — lihat
 * ConversationContextBuilder). Kalau output AI tidak bisa di-parse jadi ini, dianggap gagal
 * validasi skema dan Conversation Engine akan eskalasi ke manusia — bukan menebak-nebak isi.
 */
class AiStructuredReply
{
    public function __construct(
        public readonly string $intent,
        public readonly string $replyMessage,
        public readonly bool $escalationRequired,
        public readonly ?string $escalationReason,
        public readonly float $confidence,
    ) {}

    public static function fromJson(?string $raw): ?self
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode(self::stripMarkdownFences($raw), true);

        if (! is_array($decoded)) {
            return null;
        }

        $replyMessage = $decoded['reply_message'] ?? null;

        if (! is_string($replyMessage) || trim($replyMessage) === '') {
            return null;
        }

        if (! isset($decoded['confidence']) || ! is_numeric($decoded['confidence'])) {
            return null;
        }

        return new self(
            intent: is_string($decoded['intent'] ?? null) ? $decoded['intent'] : 'unknown',
            replyMessage: $replyMessage,
            escalationRequired: (bool) ($decoded['escalation_required'] ?? false),
            escalationReason: is_string($decoded['escalation_reason'] ?? null) ? $decoded['escalation_reason'] : null,
            confidence: (float) $decoded['confidence'],
        );
    }

    /**
     * Beberapa model kadang membungkus JSON dengan ```json ... ``` walau sudah diminta
     * tidak melakukannya — dibersihkan dulu supaya tetap bisa di-parse.
     */
    private static function stripMarkdownFences(string $raw): string
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
            $trimmed = preg_replace('/```\s*$/', '', $trimmed);
        }

        return trim((string) $trimmed);
    }
}
