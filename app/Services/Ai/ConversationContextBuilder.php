<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\KnowledgeItem;
use App\Models\Message;
use App\Models\PromptVersion;

/**
 * Susun system prompt + riwayat percakapan yang dikirim ke AiProviderContract. Knowledge
 * base yang disertakan HANYA yang published & masih berlaku (KnowledgeItem::scopeUsableByAi)
 * — draft tidak pernah bocor ke prompt.
 */
class ConversationContextBuilder
{
    private const MAX_HISTORY_MESSAGES = 20;

    private const MAX_KNOWLEDGE_ITEMS = 8;

    public function buildSystemPrompt(Business $business): string
    {
        $promptVersion = PromptVersion::where('business_id', $business->id)->where('is_active', true)->first();
        $template = $promptVersion?->content ?: $this->defaultPromptTemplate();

        $knowledgeItems = KnowledgeItem::where('business_id', $business->id)
            ->usableByAi()
            ->orderByDesc('priority')
            ->limit(self::MAX_KNOWLEDGE_ITEMS)
            ->get();

        $knowledgeText = $knowledgeItems->isEmpty()
            ? 'Belum ada knowledge base yang dipublikasikan untuk bisnis ini.'
            : $knowledgeItems->map(fn (KnowledgeItem $item) => "- [{$item->category}] {$item->title}: {$item->content}")->implode("\n");

        return strtr($template, [
            '{{business_name}}' => $business->name,
            '{{assistant_name}}' => $business->assistant_name ?: 'Asisten',
            '{{assistant_identity}}' => $business->assistant_identity ?: '-',
            '{{payment_terms}}' => $business->payment_terms ?: '-',
            '{{refund_policy}}' => $business->refund_policy ?: '-',
            '{{opt_out_instructions}}' => $business->opt_out_instructions ?: '-',
            '{{knowledge_base}}' => $knowledgeText,
        ]);
    }

    /** @return array<int, array{role: string, content: string}> */
    public function buildMessages(Conversation $conversation): array
    {
        $recent = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        return $recent->map(fn (Message $message) => [
            'role' => $message->direction === Message::DIRECTION_INBOUND ? 'user' : 'assistant',
            'content' => filled($message->body) ? $message->body : '[pesan tanpa teks — media/lampiran]',
        ])->all();
    }

    private function defaultPromptTemplate(): string
    {
        return <<<'PROMPT'
            Kamu adalah {{assistant_name}}, asisten AI sales untuk {{business_name}}. {{assistant_identity}}

            Syarat pembayaran: {{payment_terms}}
            Kebijakan refund: {{refund_policy}}
            Instruksi opt-out: {{opt_out_instructions}}

            Knowledge base (gunakan HANYA informasi ini untuk menjawab pertanyaan spesifik produk/harga/kebijakan — jangan mengarang jawaban di luar ini):
            {{knowledge_base}}

            ATURAN KETAT — WAJIB DIPATUHI:
            - Jangan pernah menjanjikan diskon, harga khusus, atau kesepakatan di luar informasi yang tersedia.
            - Jangan pernah menyatakan transaksi/penjualan sudah "deal", "berhasil", atau "won" — itu wewenang admin manusia, bukan kamu.
            - Kalau pelanggan minta bicara dengan manusia, komplain, terdengar marah, atau bertanya di luar knowledge base ini, set escalation_required=true.
            - Kalau kamu tidak yakin dengan jawabanmu, set confidence rendah (di bawah 0.5) dan escalation_required=true.

            WAJIB balas HANYA dengan JSON valid (tanpa markdown, tanpa penjelasan lain di luar JSON) persis format berikut:
            {"intent": "...", "reply_message": "...", "escalation_required": false, "escalation_reason": null, "confidence": 0.0}
            PROMPT;
    }
}
