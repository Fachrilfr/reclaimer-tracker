<?php

use App\Actions\SendStorageReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('b3:pengingat', function (SendStorageReminders $reminders) {
    $this->info("{$reminders->handle()} pengingat masa simpan terkirim ke HSE.");
})->purpose('Kirim pengingat masa simpan limbah B3 ke HSE');

// Jam pengingat harian (parameter daily_reminder_hour, PRD §8). Butuh scheduler berjalan:
// php artisan schedule:work (lokal) atau cron "* * * * * php artisan schedule:run" (server).
Schedule::command('b3:pengingat')->dailyAt('07:00')->timezone('Asia/Jakarta');
