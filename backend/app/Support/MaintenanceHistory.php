<?php

namespace App\Support;

use App\Models\MachineMaintenanceLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Periode maintenance mesin dari log mulai dan selesai (machine_maintenance_logs).
 * Dipakai untuk riwayat di halaman Status mesin dan keterangan keterlambatan
 * kirim pallet ke TPS.
 */
final class MaintenanceHistory
{
    /**
     * @return Collection<int, array{start: CarbonInterface, end: ?CarbonInterface, reason: string, started_by: ?string, end_note: ?string, ended_by: ?string}>
     */
    public static function windows(): Collection
    {
        $windows = [];
        $open = null;

        foreach (MachineMaintenanceLog::with('user')->orderBy('logged_at')->orderBy('id')->get() as $log) {
            if ($log->action === 'start') {
                $open = count($windows);
                $windows[] = [
                    'start' => $log->logged_at,
                    'end' => null,
                    'reason' => $log->reason,
                    'started_by' => $log->user?->name,
                    'end_note' => null,
                    'ended_by' => null,
                ];
            } elseif ($open !== null) {
                $windows[$open]['end'] = $log->logged_at;
                $windows[$open]['end_note'] = $log->reason;
                $windows[$open]['ended_by'] = $log->user?->name;
                $open = null;
            }
        }

        return collect($windows);
    }

    /** Periode maintenance terakhir yang beririsan dengan rentang tanggal ini. */
    public static function overlapping(Collection $windows, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return $windows
            ->filter(fn (array $w) => $w['start']->lte($to) && ($w['end'] === null || $w['end']->gte($from)))
            ->last();
    }

    public static function present(array $window): array
    {
        return [
            'start' => $window['start']->toIso8601String(),
            'end' => $window['end']?->toIso8601String(),
            'reason' => $window['reason'],
            'started_by' => $window['started_by'],
            'end_note' => $window['end_note'],
            'ended_by' => $window['ended_by'],
            'duration_minutes' => $window['end'] ? (int) round($window['start']->diffInMinutes($window['end'])) : null,
        ];
    }
}
