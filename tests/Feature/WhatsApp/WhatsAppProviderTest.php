<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\Business;
use App\Models\IntegrationCredential;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppCredentialResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusiness(): Business
    {
        return Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function giveCredentials(Business $business, string $token = 'test-token-rahasia', string $phoneNumberId = '1234567890'): void
    {
        IntegrationCredential::create([
            'business_id' => $business->id,
            'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
            'credential_key' => IntegrationCredential::WHATSAPP_KEY_TOKEN,
            'encrypted_value' => $token,
            'is_active' => true,
        ]);

        IntegrationCredential::create([
            'business_id' => $business->id,
            'provider' => IntegrationCredential::PROVIDER_WHATSAPP,
            'credential_key' => IntegrationCredential::WHATSAPP_KEY_PHONE_NUMBER_ID,
            'encrypted_value' => $phoneNumberId,
            'is_active' => true,
        ]);
    }

    public function test_container_mengikat_ke_fake_provider_secara_default_di_testing(): void
    {
        $provider = app(WhatsAppProviderContract::class);

        $this->assertInstanceOf(FakeWhatsAppProvider::class, $provider);
    }

    public function test_fake_provider_mencatat_pesan_tanpa_melakukan_http_call(): void
    {
        Http::fake();
        $business = $this->makeBusiness();

        $provider = new FakeWhatsAppProvider;
        $result = $provider->sendTextMessage($business, '+628123456789', 'Halo, ini pesan uji.');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->providerMessageId);
        $this->assertCount(1, $provider->sentMessages());
        $this->assertSame('+628123456789', $provider->sentMessages()[0]['to']);
        $this->assertSame($business->id, $provider->sentMessages()[0]['business_id']);

        Http::assertNothingSent();
    }

    public function test_meta_provider_mengirim_request_dengan_format_dan_endpoint_yang_benar(): void
    {
        $business = $this->makeBusiness();
        $this->giveCredentials($business);
        config(['services.whatsapp.api_version' => 'v20.0']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.HBgLTEST123']],
            ], 200),
        ]);

        $provider = new MetaWhatsAppProvider(new WhatsAppCredentialResolver);
        $result = $provider->sendTextMessage($business, '+628123456789', 'Halo dari AI Sales Admin.');

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
        $business = $this->makeBusiness();
        $this->giveCredentials($business);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
            ], 401),
        ]);

        $provider = new MetaWhatsAppProvider(new WhatsAppCredentialResolver);
        $result = $provider->sendTextMessage($business, '+628123456789', 'Halo.');

        $this->assertFalse($result->success);
        $this->assertSame('Invalid OAuth access token.', $result->errorMessage);
    }

    public function test_meta_provider_menolak_kirim_kalau_kredensial_bisnis_ini_belum_dikonfigurasi(): void
    {
        $business = $this->makeBusiness(); // sengaja tidak diberi kredensial sama sekali

        Http::fake();

        $provider = new MetaWhatsAppProvider(new WhatsAppCredentialResolver);

        $this->expectException(WhatsAppNotConfiguredException::class);

        try {
            $provider->sendTextMessage($business, '+628123456789', 'Halo.');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_kredensial_bisnis_lain_tidak_ikut_terpakai(): void
    {
        // Isolasi multi-tenant (Fase 8b) — kredensial bisnis A tidak boleh "nyasar" dipakai
        // untuk mengirim pesan bisnis B, walau keduanya sama-sama aktif di database.
        $businessA = $this->makeBusiness();
        $this->giveCredentials($businessA, token: 'token-milik-a', phoneNumberId: '111');

        $businessB = Business::create(['name' => 'Bisnis Lain (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $this->giveCredentials($businessB, token: 'token-milik-b', phoneNumberId: '222');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);

        (new MetaWhatsAppProvider(new WhatsAppCredentialResolver))->sendTextMessage($businessB, '+628123456789', 'Halo B.');

        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v20.0/222/messages'
            && $request->hasHeader('Authorization', 'Bearer token-milik-b'));
    }
}
