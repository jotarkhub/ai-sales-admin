<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Models\Escalation;

/**
 * Satu-satunya tempat yang memutuskan apakah balasan AI boleh dikirim ke pelanggan atau
 * wajib dieskalasi ke manusia. Sesuai docs/ARCHITECTURE.md sequence "Percakapan WhatsApp":
 * output AI TIDAK PERNAH langsung dikirim tanpa lolos guardrail ini dulu.
 */
class ConversationGuardrailService
{
    private const DEFAULT_MIN_CONFIDENCE = 0.5;

    public function evaluate(?AiStructuredReply $reply, Business $business): GuardrailResult
    {
        if ($reply === null) {
            return GuardrailResult::escalate(
                Escalation::REASON_LOW_CONFIDENCE,
                'Output AI tidak berbentuk JSON terstruktur yang valid (gagal validasi skema).',
            );
        }

        if ($reply->escalationRequired) {
            return GuardrailResult::escalate(
                $reply->escalationReason ?: Escalation::REASON_CUSTOMER_REQUESTED_HUMAN,
                $reply->escalationReason ?: 'AI menandai percakapan ini perlu ditangani manusia.',
            );
        }

        $minConfidence = (float) data_get($business->ai_authority_limit, 'min_confidence', self::DEFAULT_MIN_CONFIDENCE);

        if ($reply->confidence < $minConfidence) {
            return GuardrailResult::escalate(
                Escalation::REASON_LOW_CONFIDENCE,
                "Confidence AI ({$reply->confidence}) di bawah ambang batas bisnis ({$minConfidence}).",
            );
        }

        return GuardrailResult::pass();
    }
}
