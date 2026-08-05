<?php

namespace App\Contracts;

use App\Services\WhatsApp\WhatsAppSendResult;

/**
 * Abstraksi pengiriman WhatsApp — SATU-SATUNYA cara kode lain (job follow-up, conversation
 * engine di Fase 4, dst.) mengirim pesan. Jangan panggil HTTP client WhatsApp langsung di
 * tempat lain supaya provider bisa diganti (mis. saat migrasi vendor) tanpa ubah caller.
 */
interface WhatsAppProviderContract
{
    public function sendTextMessage(string $to, string $body): WhatsAppSendResult;
}
