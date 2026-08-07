<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Models\Business;
use Illuminate\Support\Str;

/**
 * HANYA boleh dipakai saat APP_ENV=testing (ditegakkan App\Support\ProviderGuard saat
 * aplikasi boot). Tidak melakukan panggilan jaringan apa pun — cuma mencatat pesan yang
 * "terkirim" di memori supaya test bisa assert tanpa mock HTTP.
 */
class FakeWhatsAppProvider implements WhatsAppProviderContract
{
    /** @var array<int, array{business_id: int, to: string, body: string}> */
    private array $sentMessages = [];

    public function sendTextMessage(Business $business, string $to, string $body): WhatsAppSendResult
    {
        $this->sentMessages[] = ['business_id' => $business->id, 'to' => $to, 'body' => $body];

        return WhatsAppSendResult::success(providerMessageId: 'fake-'.Str::uuid()->toString());
    }

    /** @return array<int, array{business_id: int, to: string, body: string}> */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }
}
