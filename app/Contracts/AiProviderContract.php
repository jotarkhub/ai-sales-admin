<?php

namespace App\Contracts;

use App\Services\Ai\AiReplyResult;

/**
 * Abstraksi generate balasan AI — SATU-SATUNYA cara kode lain (Conversation Engine di Fase
 * 4 lanjutan) memanggil model bahasa. Jangan panggil HTTP client OpenAI langsung di tempat
 * lain supaya provider bisa diganti tanpa ubah caller.
 *
 * @param  array<int, array{role: string, content: string}>  $messages  Riwayat percakapan
 *                                                                      format chat standar (role: 'user'|'assistant'), urut dari yang paling lama.
 */
interface AiProviderContract
{
    public function generateReply(array $messages, ?string $systemPrompt = null): AiReplyResult;
}
