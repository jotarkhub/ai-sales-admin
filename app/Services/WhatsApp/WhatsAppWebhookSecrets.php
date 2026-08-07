<?php

namespace App\Services\WhatsApp;

/** Value object hasil resolusi App Secret + Verify Token satu bisnis — lihat WhatsAppCredentialResolver. */
final class WhatsAppWebhookSecrets
{
    public function __construct(
        public readonly string $appSecret,
        public readonly string $verifyToken,
    ) {}
}
