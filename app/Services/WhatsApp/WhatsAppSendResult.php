<?php

namespace App\Services\WhatsApp;

/**
 * Hasil apa adanya dari upaya kirim WhatsApp — tidak pernah "sukses palsu". Kalau provider
 * gagal, success=false dan errorMessage wajib terisi; caller (mis. job follow-up) yang
 * memutuskan retry/tandai gagal, bukan provider yang berpura-pura berhasil.
 */
class WhatsAppSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
    ) {}

    public static function success(string $providerMessageId, array $rawResponse = []): self
    {
        return new self(success: true, providerMessageId: $providerMessageId, rawResponse: $rawResponse);
    }

    public static function failure(string $errorMessage, array $rawResponse = []): self
    {
        return new self(success: false, errorMessage: $errorMessage, rawResponse: $rawResponse);
    }
}
