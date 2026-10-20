<?php

namespace App\Notifications;

use App\Models\Machine;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan di aplikasi saat mesin mulai atau selesai maintenance (FR-21).
 * Kanal email atau pesan bisa ditambahkan di via() setelah diputuskan (FR-32).
 */
class MachineStatusChanged extends Notification
{
    public function __construct(
        private readonly Machine $machine,
        private readonly string $kind,
        private readonly string $reason,
        private readonly string $actor,
        private readonly ?int $durationMinutes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $started = $this->kind === 'maintenance_started';

        return [
            'kind' => $this->kind,
            'title' => $started
                ? "Mesin {$this->machine->code} sedang maintenance"
                : "Mesin {$this->machine->code} kembali beroperasi",
            'message' => $started
                ? "Alasan: {$this->reason}. Proses batch dan pengiriman residu ke TPS terhenti. Dilaporkan oleh {$this->actor}."
                : "Catatan perbaikan: {$this->reason}. Dicatat oleh {$this->actor}.",
            'duration_minutes' => $this->durationMinutes,
            'url' => '/mesin',
        ];
    }
}
