<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\MachineMaintenanceLog;
use App\Models\User;
use App\Notifications\MachineStatusChanged;
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
    public function startMaintenance(Request $request, string $reason, ?string $expectedEnd): Machine
    {
        $machine = DB::transaction(function () use ($request, $reason, $expectedEnd) {
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();

            if ($machine->isUnderMaintenance()) {
                throw ValidationException::withMessages([
                    'reason' => "Mesin {$machine->code} sudah berstatus maintenance sejak "
                        . $machine->maintenance_started_at->locale('id')->translatedFormat('j M Y, H.i') . '.',
                ]);
            }

            $machine->update([
                'status' => 'maintenance',
                'maintenance_reason' => $reason,
                'maintenance_started_at' => now(),
                'maintenance_expected_end_at' => $expectedEnd ? Carbon::parse($expectedEnd) : null,
                'maintenance_reported_by' => $request->user()->id,
            ]);
            $this->log($request, $machine, 'start', $reason);
            AuditLog::record($request, 'maintenance_start', $machine, [
                'reason' => $reason,
                'expected_end_at' => $machine->maintenance_expected_end_at?->toDateTimeString(),
            ]);

            return $machine;
        });

        $this->notify($request, new MachineStatusChanged($machine, 'maintenance_started', $reason, $request->user()->name));

        return $machine;
    }

    public function endMaintenance(Request $request, string $note): Machine
    {
        [$machine, $minutes] = DB::transaction(function () use ($request, $note) {
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();

            if (! $machine->isUnderMaintenance()) {
                throw ValidationException::withMessages([
                    'note' => "Mesin {$machine->code} tidak sedang maintenance.",
                ]);
            }

            $minutes = (int) round($machine->maintenance_started_at->diffInMinutes(now()));
            $before = $machine->only(['maintenance_reason', 'maintenance_started_at']);

            $machine->update([
                'status' => 'operating',
                'maintenance_reason' => null,
                'maintenance_started_at' => null,
                'maintenance_expected_end_at' => null,
                'maintenance_reported_by' => null,
            ]);
            $this->log($request, $machine, 'end', $note);
            AuditLog::record($request, 'maintenance_end', $machine, ['note' => $note, 'duration_minutes' => $minutes], $before);

            return [$machine, $minutes];
        });

        $this->notify($request, new MachineStatusChanged($machine, 'maintenance_ended', $note, $request->user()->name, $minutes));

        return $machine;
    }

    private function log(Request $request, Machine $machine, string $action, string $reason): void
    {
        MachineMaintenanceLog::create([
            'machine_id' => $machine->id,
            'action' => $action,
            'reason' => $reason,
            'user_id' => $request->user()->id,
            'logged_at' => now(),
            'notified_hse' => true,
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
