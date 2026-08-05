<?php

namespace Tests\Unit\Support;

use App\Support\ProviderGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProviderGuardTest extends TestCase
{
    public function test_menolak_jika_production_dan_provider_fake(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak boleh .fake. saat APP_ENV=production/');

        ProviderGuard::assertNotFakeInProduction('production', 'fake', 'WhatsApp');
    }

    public function test_lolos_jika_production_dan_provider_asli(): void
    {
        ProviderGuard::assertNotFakeInProduction('production', 'meta', 'WhatsApp');
        $this->addToAssertionCount(1);
    }

    public function test_lolos_jika_testing_dan_provider_fake(): void
    {
        ProviderGuard::assertNotFakeInProduction('testing', 'fake', 'WhatsApp');
        $this->addToAssertionCount(1);
    }

    public function test_lolos_jika_local_dan_provider_fake(): void
    {
        ProviderGuard::assertNotFakeInProduction('local', 'fake', 'WhatsApp');
        $this->addToAssertionCount(1);
    }
}
