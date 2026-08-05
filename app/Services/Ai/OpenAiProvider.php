<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderContract;
use App\Exceptions\AiNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Implementasi nyata OpenAI Chat Completions API — CREDENTIAL_REQUIRED sampai
 * OPENAI_API_KEY diisi. Tidak pernah dipanggil selama AI_PROVIDER=fake (lihat
 * App\Support\ProviderGuard).
 *
 * Referensi: https://platform.openai.com/docs/api-reference/chat/create
 */
class OpenAiProvider implements AiProviderContract
{
    private const BASE_URL = 'https://api.openai.com/v1/chat/completions';

    public function generateReply(array $messages, ?string $systemPrompt = null): AiReplyResult
    {
        $apiKey = config('services.ai.api_key');

        if (blank($apiKey)) {
            throw new AiNotConfiguredException(
                'API key OpenAI belum dikonfigurasi. Isi OPENAI_API_KEY di .env (lihat '.
                'docs/ARCHITECTURE.md #9), atau pakai AI_PROVIDER=fake untuk lingkungan testing.'
            );
        }

        $payloadMessages = $systemPrompt !== null
            ? [['role' => 'system', 'content' => $systemPrompt], ...$messages]
            : $messages;

        $response = Http::withToken($apiKey)->post(self::BASE_URL, [
            'model' => config('services.ai.model', 'gpt-4o-mini'),
            'messages' => $payloadMessages,
        ]);

        if ($response->successful()) {
            $content = $response->json('choices.0.message.content');

            if ($content === null) {
                return AiReplyResult::failure('Respons OpenAI tidak berisi content.', $response->json() ?? []);
            }

            return AiReplyResult::success(
                content: $content,
                usage: $response->json('usage') ?? [],
                rawResponse: $response->json() ?? [],
            );
        }

        return AiReplyResult::failure(
            errorMessage: $response->json('error.message') ?? "HTTP {$response->status()} tanpa pesan error dari OpenAI.",
            rawResponse: $response->json() ?? [],
        );
    }
}
