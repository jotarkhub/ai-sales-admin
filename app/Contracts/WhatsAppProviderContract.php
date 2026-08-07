<?php

namespace App\Contracts;

use App\Models\Business;
use App\Services\WhatsApp\WhatsAppSendResult;

/**
 * Abstraksi pengiriman WhatsApp — SATU-SATUNYA cara kode lain (job follow-up, conversation
 * engine, dst.) mengirim pesan. Jangan panggil HTTP client WhatsApp langsung di tempat lain
 * supaya provider bisa diganti (mis. saat migrasi vendor) tanpa ubah caller.
 *
 * $business WAJIB ada sejak Fase 8b — tiap bisnis (klien) punya App Meta & nomor WhatsApp
 * sendiri (lihat docs/STATUS.md "Fase 8 — Platform Multi-Tenant"), jadi kredensial mana yang
 * dipakai untuk sekali kirim bergantung pesan itu punya bisnis mana, bukan konfigurasi global.
 */
interface WhatsAppProviderContract
{
    public function sendTextMessage(Business $business, string $to, string $body): WhatsAppSendResult;
}
