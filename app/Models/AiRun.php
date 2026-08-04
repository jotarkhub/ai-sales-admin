<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_FAKE = 'fake';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_GUARDRAIL_BLOCKED = 'guardrail_blocked';

    protected $fillable = [
        'conversation_id', 'message_id', 'prompt_version_id', 'provider', 'model_used',
        'input_tokens', 'output_tokens', 'estimated_cost_usd', 'latency_ms', 'raw_output',
        'structured_output_valid', 'status',
    ];

    protected function casts(): array
    {
        return [
            'raw_output' => 'array',
            'structured_output_valid' => 'boolean',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }
}
