<?php

namespace App\Notifications;

use App\Models\B3Pallet;
use Illuminate\Notifications\Notification;

/**
 * Pengingat masa simpan pallet limbah B3 ke HSE (FR-27, FR-28): sisa hari eksak.
 * Kanal email atau pesan bisa ditambahkan di via() setelah diputuskan (FR-32).
 */
class StorageReminder extends Notification
{
    public function __construct(
        private readonly B3Pallet $pallet,
        private readonly string $level,
        private readonly int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public static function message(B3Pallet $pallet, string $level, int $days): array
    {
        $remaining = match (true) {
            $days < 0 => 'sudah lewat ' . -$days . ' hari',
            $days === 0 => 'batasnya hari ini',
            default => "sisa {$days} hari",
        };

        return [
            'title' => "Pallet {$pallet->pallet_number} {$level}: masa simpan {$remaining}",
            'message' => "Pallet di TPS, batas simpan {$pallet->expiry_date->locale('id')->translatedFormat('j M Y')}. "
                . 'Serahkan ke vendor lewat surat jalan.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'storage_reminder', 'level' => $this->level, 'days_remaining' => $this->daysRemaining]
            + self::message($this->pallet, $this->level, $this->daysRemaining)
            + ['url' => "/limbah-b3?lokasi=semua&sorot=pallet-{$this->pallet->id}"];
    }
}
