<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\FollowUpDispatchService;
use Illuminate\Console\Command;

/**
 * Entry point manual/terjadwal untuk mengirim follow_ups yang sudah jatuh tempo.
 * Lihat routes/console.php untuk penjadwalan otomatis (butuh cron `schedule:run` aktif
 * di server produksi — belum relevan selama masih dev di komputer lokal).
 */
class SendDueFollowUps extends Command
{
    protected $signature = 'follow-ups:send';

    protected $description = 'Kirim semua follow-up WhatsApp yang sudah jatuh tempo (status pending, scheduled_at <= sekarang).';

    public function handle(FollowUpDispatchService $dispatcher): int
    {
        $result = $dispatcher->dispatchDue();

        $this->info(sprintf(
            'Follow-up diproses — terkirim: %d, dilewati: %d, dijadwal ulang: %d, gagal permanen: %d.',
            $result['sent'],
            $result['skipped'],
            $result['retry_scheduled'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
