<?php

namespace App\Actions;

use App\Models\B3NotificationLog;
use App\Models\B3Pallet;
use App\Models\User;
use App\Notifications\StorageReminder;
use App\Support\StorageStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat masa simpan harian ke HSE (FR-27). Dijalankan tiap pukul 07.00 lewat
 * scheduler (routes/console.php), atau manual: php artisan b3:pengingat.
 *
 * Perhatian dan mendesak dikirim sekali saat pallet pertama kali masuk jenjang itu.
 * Kritis dikirim ulang tiap hari sampai pallet diserahkan lewat surat jalan.
 */
class SendStorageReminders
{
    /** @return int jumlah pengingat yang dikirim */
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $recipients = User::whereIn('role', config('reclaimer.b3_reminder_roles'))->where('is_active', true)->get();
        $thresholds = StorageStatus::thresholds();
        $sent = 0;

        // Masa simpan baru berjalan setelah pallet sampai di TPS
        $pallets = B3Pallet::where('status', '!=', 'transported')->whereNotNull('sent_to_tps_at')->orderBy('expiry_date')->get();
        foreach ($pallets as $pallet) {
            $status = StorageStatus::forPallet($pallet, $now, $thresholds);
            $type = B3NotificationLog::TYPES[$status->level] ?? null;
            if (! $type || $this->alreadySent($pallet, $type, $now)) {
                continue;
            }

            B3NotificationLog::create([
                'pallet_id' => $pallet->id,
                'notification_type' => $type,
                'days_remaining' => $status->daysRemaining,
                'recipient_role' => implode(',', config('reclaimer.b3_reminder_roles')),
                'message' => StorageReminder::message($pallet, $status->level, $status->daysRemaining)['title'],
                'sent_at' => $now,
                'created_at' => $now,
            ]);
            Notification::send($recipients, new StorageReminder($pallet, $status->level, $status->daysRemaining));
            $sent++;
        }

        return $sent;
    }

    private function alreadySent(B3Pallet $pallet, string $type, CarbonInterface $now): bool
    {
        $query = B3NotificationLog::where('pallet_id', $pallet->id)->where('notification_type', $type);

        // Kritis diulang tiap hari; jenjang lain cukup sekali
        if ($type === B3NotificationLog::TYPES['kritis']) {
            $query->whereDate('sent_at', $now->toDateString());
        }

        return $query->exists();
    }
}
