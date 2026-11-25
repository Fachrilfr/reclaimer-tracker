<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\MachineMaintenanceLog;
use App\Models\User;
use App\Notifications\MachineStatusChanged;
use App\Support\MaintenanceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Mulai dan akhiri maintenance mesin (FR-20), lalu beri tahu HSE, supervisor,
 * manajemen, dan admin di aplikasi (FR-21).
 */
class SetMachineStatus
{
    public function recordCompleted(Request $request, Carbon $start, Carbon $end, string $reason, string $note): Machine
    {
        return DB::transaction(function () use ($request, $start, $end, $reason, $note) {
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();
            $this->ensureAvailable($start, $end);
            $this->log($request, $machine, 'start', $reason, $start, false);
            $this->log($request, $machine, 'end', $note, $end, false);
            AuditLog::record($request, 'maintenance_history', $machine, ['started_at' => $start, 'ended_at' => $end]);

            return $machine;
        });
    }

    private function ensureAvailable(Carbon $start, ?Carbon $end): void
    {
        foreach (MaintenanceHistory::windows() as $window) {
            if ((! $end || $window['start']->lte($end)) && (! $window['end'] || $window['end']->gt($start))) {
                throw ValidationException::withMessages(['started_at' => 'Waktu maintenance bertumpang tindih dengan riwayat mesin.']);
            }
        }
    }

    public function startMaintenance(Request $request, string $reason, ?Carbon $start = null): Machine
    {
        $machine = DB::transaction(function () use ($request, $reason, $start) {
            $start ??= now();
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();

            if ($machine->isUnderMaintenance()) {
                throw ValidationException::withMessages([
                    'reason' => "Mesin {$machine->code} sudah berstatus maintenance sejak "
                        .$machine->maintenance_started_at->locale('id')->translatedFormat('j M Y, H.i').'.',
                ]);
            }

            $this->ensureAvailable($start, null);
            $machine->update([
                'status' => 'maintenance',
                'maintenance_reason' => $reason,
                'maintenance_started_at' => $start,
                // Perkiraan selesai dihapus (meeting 30 Okt 2026); kolomnya tetap kosong
                'maintenance_expected_end_at' => null,
                'maintenance_reported_by' => $request->user()->id,
            ]);
            $this->log($request, $machine, 'start', $reason, $start);
            AuditLog::record($request, 'maintenance_start', $machine, ['reason' => $reason]);

            return $machine;
        });

        $this->notify($request, new MachineStatusChanged($machine, 'maintenance_started', $reason, $request->user()->name));

        return $machine;
    }

    public function endMaintenance(Request $request, string $note, ?Carbon $end = null): Machine
    {
        [$machine, $minutes] = DB::transaction(function () use ($request, $note, $end) {
            $end ??= now();
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();

            if (! $machine->isUnderMaintenance()) {
                throw ValidationException::withMessages([
                    'note' => "Mesin {$machine->code} tidak sedang maintenance.",
                ]);
            }

            if ($end->lt($machine->maintenance_started_at)) {
                throw ValidationException::withMessages(['ended_at' => 'Waktu selesai tidak boleh sebelum mulai maintenance.']);
            }
            $minutes = (int) round($machine->maintenance_started_at->diffInMinutes($end));
            $before = $machine->only(['maintenance_reason', 'maintenance_started_at']);

            $machine->update([
                'status' => 'operating',
                'maintenance_reason' => null,
                'maintenance_started_at' => null,
                'maintenance_expected_end_at' => null,
                'maintenance_reported_by' => null,
            ]);
            $this->log($request, $machine, 'end', $note, $end);
            AuditLog::record($request, 'maintenance_end', $machine, ['note' => $note, 'duration_minutes' => $minutes], $before);

            return [$machine, $minutes];
        });

        $this->notify($request, new MachineStatusChanged($machine, 'maintenance_ended', $note, $request->user()->name, $minutes));

        return $machine;
    }

    private function log(Request $request, Machine $machine, string $action, string $reason, Carbon $time, bool $notified = true): void
    {
        MachineMaintenanceLog::create([
            'machine_id' => $machine->id,
            'action' => $action,
            'reason' => $reason,
            'user_id' => $request->user()->id,
            'logged_at' => $time,
            'notified_hse' => $notified,
            'created_at' => now(),
        ]);
    }

    /** Pelapor sendiri tidak perlu diberi tahu. */
    private function notify(Request $request, MachineStatusChanged $notification): void
    {
        $recipients = User::whereIn('role', config('reclaimer.maintenance_notify_roles'))
            ->where('is_active', true)
            ->whereKeyNot($request->user()->id)
            ->get();

        Notification::send($recipients, $notification);
    }
}
