<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiProviderContract;
use App\Exceptions\AiNotConfiguredException;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    public function test_container_mengikat_ke_fake_provider_secara_default_di_testing(): void
    {
        $provider = app(AiProviderContract::class);

        $this->assertInstanceOf(FakeAiProvider::class, $provider);
    }

    public function test_fake_provider_mencatat_pemanggilan_tanpa_http_call(): void
    {
        Http::fake();

        $provider = new FakeAiProvider;
        $messages = [['role' => 'user', 'content' => 'Halo, ada promo apa?']];

        $result = $provider->generateReply($messages, systemPrompt: 'Kamu adalah asisten sales.');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->content);
        $this->assertCount(1, $provider->calls());
        $this->assertSame($messages, $provider->calls()[0]['messages']);

        Http::assertNothingSent();
    }

    public function test_fake_provider_balasan_bisa_diatur_untuk_simulasi_skenario(): void
    {
        $provider = new FakeAiProvider;
        $provider->respondWith('Baik, promo bulan ini diskon 10%.');

        $result = $provider->generateReply([['role' => 'user', 'content' => 'Ada promo?']]);

        $this->assertSame('Baik, promo bulan ini diskon 10%.', $result->content);
    }

    public function test_openai_provider_mengirim_request_dengan_format_dan_endpoint_yang_benar(): void
    {
        config([
            'services.ai.api_key' => 'sk-test-rahasia',
            'services.ai.model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo! Ada yang bisa dibantu?']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8, 'total_tokens' => 18],
            ], 200),
        ]);

        $provider = new OpenAiProvider;
        $result = $provider->generateReply(
            messages: [['role' => 'user', 'content' => 'Halo']],
            systemPrompt: 'Kamu adalah asisten sales yang ramah.',
        );

        $this->assertTrue($result->success);
        $this->assertSame('Halo! Ada yang bisa dibantu?', $result->content);
        $this->assertSame(18, $result->usage['total_tokens']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer sk-test-rahasia')
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][0]['content'] === 'Kamu adalah asisten sales yang ramah.'
                && $request['messages'][1]['role'] === 'user'
                && $request['messages'][1]['content'] === 'Halo';
        });
    }

    public function test_openai_provider_tanpa_system_prompt_tidak_menambah_pesan_system(): void
    {
        config(['services.ai.api_key' => 'sk-test-rahasia']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Oke.']]],
            ], 200),
        ]);

        $provider = new OpenAiProvider;
        $provider->generateReply([['role' => 'user', 'content' => 'Hai']]);

        Http::assertSent(fn ($request) => count($request['messages']) === 1 && $request['messages'][0]['role'] === 'user');
    }

    public function test_openai_provider_menangani_response_gagal(): void
    {
        config(['services.ai.api_key' => 'sk-test-rahasia']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Incorrect API key provided.'],
            ], 401),
        ]);

        $provider = new OpenAiProvider;
        $result = $provider->generateReply([['role' => 'user', 'content' => 'Hai']]);

        $this->assertFalse($result->success);
        $this->assertSame('Incorrect API key provided.', $result->errorMessage);
    }

    public function test_openai_provider_menolak_generate_kalau_api_key_belum_dikonfigurasi(): void
    {
        config(['services.ai.api_key' => null]);

        Http::fake();

        $provider = new OpenAiProvider;

        $this->expectException(AiNotConfiguredException::class);

        try {
            $provider->generateReply([['role' => 'user', 'content' => 'Hai']]);
        } finally {
            Http::assertNothingSent();
        }
    }
}
