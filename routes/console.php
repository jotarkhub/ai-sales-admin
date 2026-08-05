<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kirim follow-up WhatsApp yang jatuh tempo tiap menit. Baru benar-benar berjalan otomatis
// di production kalau cron server menjalankan `php artisan schedule:run` tiap menit — lihat
// docs/ARCHITECTURE.md untuk catatan deployment. Aman dijalankan manual kapan saja:
// `php artisan follow-ups:send`.
Schedule::command('follow-ups:send')->everyMinute()->withoutOverlapping();
