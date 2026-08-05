<?php

namespace App\Providers;

use App\Contracts\WhatsAppProviderContract;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Support\ProviderGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerWhatsAppProvider();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Bind App\Contracts\WhatsAppProviderContract ke implementasi sesuai
     * services.whatsapp.provider. Menolak boot kalau production tapi provider masih fake
     * (App\Support\ProviderGuard) — lihat docs/STATUS.md "Provider Fake — Aturan Keras".
     */
    private function registerWhatsAppProvider(): void
    {
        $provider = config('services.whatsapp.provider', 'fake');

        ProviderGuard::assertNotFakeInProduction($this->app->environment(), $provider, 'WhatsApp');

        $this->app->singleton(WhatsAppProviderContract::class, function () use ($provider) {
            return $provider === 'meta'
                ? new MetaWhatsAppProvider
                : new FakeWhatsAppProvider;
        });
    }
}
