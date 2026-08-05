<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderContract;

/**
 * HANYA boleh dipakai saat APP_ENV=testing (ditegakkan App\Support\ProviderGuard saat
 * aplikasi boot). Tidak memanggil OpenAI sama sekali — balasan bisa diatur lewat
 * respondWith() supaya test bisa mensimulasikan berbagai skenario (normal, gagal, dst.).
 */
class FakeAiProvider implements AiProviderContract
{
    /** @var array<int, array{messages: array, systemPrompt: ?string}> */
    private array $calls = [];

    private string $canned = '[BALASAN AI PALSU — hanya untuk testing, tidak pernah dikirim ke pelanggan sungguhan]';

    public function generateReply(array $messages, ?string $systemPrompt = null): AiReplyResult
    {
        $this->calls[] = ['messages' => $messages, 'systemPrompt' => $systemPrompt];

        return AiReplyResult::success($this->canned);
    }

    public function respondWith(string $content): void
    {
        $this->canned = $content;
    }

    /** @return array<int, array{messages: array, systemPrompt: ?string}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
