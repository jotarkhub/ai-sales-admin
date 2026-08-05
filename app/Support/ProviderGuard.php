<?php

namespace App\Support;

use RuntimeException;

/**
 * Menegakkan aturan keras di docs/STATUS.md: "Aplikasi wajib menolak boot apabila
 * APP_ENV=production tetapi provider=fake". Fungsi murni (tidak menyentuh app()/config()
 * langsung) supaya gampang di-unit-test tanpa perlu memanipulasi environment aplikasi
 * sungguhan.
 */
class ProviderGuard
{
    public static function assertNotFakeInProduction(string $environment, string $provider, string $label): void
    {
        if ($environment === 'production' && $provider === 'fake') {
            throw new RuntimeException(
                "Konfigurasi tidak valid: {$label} provider tidak boleh 'fake' saat APP_ENV=production. ".
                'Fake provider hanya untuk testing — lihat docs/STATUS.md bagian "Provider Fake — Aturan Keras".'
            );
        }
    }
}
