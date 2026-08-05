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

    /**
     * Default-nya JSON terstruktur valid (bukan escalation, confidence tinggi) supaya test
     * yang tidak peduli isi balasan AI (mis. test webhook) tetap mengikuti jalur "sukses"
     * secara default. Test yang mau menguji jalur eskalasi/gagal panggil respondWith().
     */
    private string $canned = '{"intent":"general_inquiry","reply_message":"[BALASAN AI PALSU — hanya untuk testing, tidak pernah dikirim ke pelanggan sungguhan] Terima kasih sudah menghubungi kami, ada yang bisa dibantu?","escalation_required":false,"escalation_reason":null,"confidence":0.9}';

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
