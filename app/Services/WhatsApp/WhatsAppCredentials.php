<?php

namespace App\Services\WhatsApp;

/** Value object hasil resolusi kredensial WhatsApp satu bisnis — lihat WhatsAppCredentialResolver. */
final class WhatsAppCredentials
{
    public function __construct(
        public readonly string $token,
        public readonly string $phoneNumberId,
        public readonly string $apiVersion,
    ) {}
}
