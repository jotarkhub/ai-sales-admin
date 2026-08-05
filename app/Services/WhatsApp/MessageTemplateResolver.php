<?php

namespace App\Services\WhatsApp;

use App\Models\Business;

/**
 * Ambil teks pesan dari Business::message_templates (dikonfigurasi admin lewat halaman
 * Konfigurasi Bisnis) berdasarkan trigger_type follow-up. Sengaja terpisah dari
 * FollowUpDispatchService supaya alias key gampang ditambah tanpa menyentuh logika kirim.
 */
class MessageTemplateResolver
{
    /**
     * Alias: beberapa trigger_type follow-up memetakan ke key template yang sudah lebih
     * dulu dipakai admin di Konfigurasi Bisnis (mis. "form_submitted_initial_message" ->
     * key "auto_reply_awal" yang sudah diisi user).
     */
    private const ALIASES = [
        'form_submitted_initial_message' => 'auto_reply_awal',
    ];

    public function resolve(Business $business, string $triggerType): ?string
    {
        $templates = $business->message_templates ?? [];

        if (! empty($templates[$triggerType])) {
            return $templates[$triggerType];
        }

        $alias = self::ALIASES[$triggerType] ?? null;

        if ($alias !== null && ! empty($templates[$alias])) {
            return $templates[$alias];
        }

        return null;
    }
}
