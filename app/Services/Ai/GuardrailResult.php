<?php

namespace App\Services\Ai;

class GuardrailResult
{
    public function __construct(
        public readonly bool $escalate,
        public readonly ?string $reason = null,
        public readonly ?string $detail = null,
    ) {}

    public static function pass(): self
    {
        return new self(escalate: false);
    }

    public static function escalate(string $reason, string $detail): self
    {
        return new self(escalate: true, reason: $reason, detail: $detail);
    }
}
