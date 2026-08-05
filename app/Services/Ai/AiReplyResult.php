<?php

namespace App\Services\Ai;

/**
 * Hasil apa adanya dari upaya generate balasan AI — tidak pernah "sukses palsu". Kalau
 * provider gagal, success=false dan errorMessage wajib terisi; caller (Conversation Engine
 * di Fase 4 lanjutan) yang memutuskan eskalasi ke manusia, bukan provider yang mengarang
 * balasan kosong.
 */
class AiReplyResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $content = null,
        public readonly ?string $errorMessage = null,
        public readonly array $usage = [],
        public readonly array $rawResponse = [],
    ) {}

    public static function success(string $content, array $usage = [], array $rawResponse = []): self
    {
        return new self(success: true, content: $content, usage: $usage, rawResponse: $rawResponse);
    }

    public static function failure(string $errorMessage, array $rawResponse = []): self
    {
        return new self(success: false, errorMessage: $errorMessage, rawResponse: $rawResponse);
    }
}
