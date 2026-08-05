<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppProviderTest extends TestCase
{
    public function test_container_mengikat_ke_fake_provider_secara_default_di_testing(): void
    {
        $provider = app(WhatsAppProviderContract::class);

        $this->assertInstanceOf(FakeWhatsAppProvider::class, $provider);
    }

    public function test_fake_provider_mencatat_pesan_tanpa_melakukan_http_call(): void
    {
        Http::fake();

        $provider = new FakeWhatsAppProvider;
        $result = $provider->sendTextMessage('+628123456789', 'Halo, ini pesan uji.');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->providerMessageId);
        $this->assertCount(1, $provider->sentMessages());
        $this->assertSame('+628123456789', $provider->sentMessages()[0]['to']);

        Http::assertNothingSent();
    }

    public function test_meta_provider_mengirim_request_dengan_format_dan_endpoint_yang_benar(): void
    {
        config([
            'services.whatsapp.token' => 'test-token-rahasia',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v20.0',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.HBgLTEST123']],
            ], 200),
        ]);

        $provider = new MetaWhatsAppProvider;
        $result = $provider->sendTextMessage('+628123456789', 'Halo dari AI Sales Admin.');

        $this->assertTrue($result->success);
        $this->assertSame('wamid.HBgLTEST123', $result->providerMessageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token-rahasia')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '+628123456789'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Halo dari AI Sales Admin.';
        });
    }

    public function test_meta_provider_menangani_response_gagal_dari_meta(): void
    {
        config([
            'services.whatsapp.token' => 'test-token-rahasia',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
            ], 401),
        ]);

        $provider = new MetaWhatsAppProvider;
        $result = $provider->sendTextMessage('+628123456789', 'Halo.');

        $this->assertFalse($result->success);
        $this->assertSame('Invalid OAuth access token.', $result->errorMessage);
    }

    public function test_meta_provider_menolak_kirim_kalau_kredensial_belum_dikonfigurasi(): void
    {
        config([
            'services.whatsapp.token' => null,
            'services.whatsapp.phone_number_id' => null,
        ]);

        Http::fake();

        $provider = new MetaWhatsAppProvider;

        $this->expectException(WhatsAppNotConfiguredException::class);

        try {
            $provider->sendTextMessage('+628123456789', 'Halo.');
        } finally {
            Http::assertNothingSent();
        }
    }
}
