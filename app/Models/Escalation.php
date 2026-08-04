<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Escalation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_RESOLVED = 'resolved';

    // Alasan eskalasi baku (lihat modul Human Handoff di spesifikasi).
    public const REASON_CUSTOMER_REQUESTED_HUMAN = 'customer_requested_human';

    public const REASON_COMPLAINT = 'complaint';

    public const REASON_ANGRY_OR_THREATENING = 'angry_or_threatening';

    public const REASON_DISCOUNT_REQUEST = 'discount_request';

    public const REASON_OUT_OF_POLICY_NEGOTIATION = 'out_of_policy_negotiation';

    public const REASON_LEGAL_QUESTION = 'legal_question';

    public const REASON_REFUND_REQUEST = 'refund_request';

    public const REASON_KNOWLEDGE_NOT_FOUND = 'knowledge_not_found';

    public const REASON_LOW_CONFIDENCE = 'low_confidence';

    public const REASON_SENSITIVE_DATA = 'sensitive_data';

    public const REASON_TRANSACTION_LIMIT_EXCEEDED = 'transaction_limit_exceeded';

    public const REASON_REPEATED_FAILURE = 'repeated_failure';

    public const REASON_READY_TO_PURCHASE = 'ready_to_purchase';

    protected $fillable = [
        'conversation_id', 'lead_id', 'reason', 'reason_detail', 'status',
        'ai_confidence_at_escalation', 'suggested_action', 'claimed_by', 'claimed_at',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_confidence_at_escalation' => 'decimal:3',
            'claimed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
