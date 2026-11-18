<?php

namespace App\Actions\Corrections;

use App\Models\Machine;
use App\Models\MachineMaintenanceLog;
use App\Support\Correction;
use App\Support\MaintenanceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi dan hapus satu periode maintenance (log mulai dan log selesainya): waktu mulai,
 * waktu selesai, alasan, dan catatan perbaikan. Periode tidak boleh bertumpuk dengan
 * periode lain. Periode yang masih berlangsung ikut mengubah status mesin; bila dihapus,
 * mesin kembali beroperasi. Tidak ada pemberitahuan baru yang dikirim.
 */
class CorrectMaintenance
{
    /** @param  array{start: string, end: ?string, reason: string, end_note: ?string}  $data  waktu dalam format Y-m-d H:i */
    public function update(Request $request, MachineMaintenanceLog $log, array $data, string $reason): string
    {
        return DB::transaction(function () use ($request, $log, $data, $reason) {
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();
            [$window, $previous, $next] = $this->window($log);
            $open = $window['end_id'] === null;
            if (! $open && ! filled($data['end'] ?? null)) {
                throw ValidationException::withMessages(['end' => 'Isi waktu selesai maintenance.']);
            }

            $start = Carbon::parse($data['start'])->startOfMinute();
            $end = $open ? null : Carbon::parse((string) $data['end'])->startOfMinute();
            $this->ensureValid($start, $end, $open, $data, $previous, $next);

            $values = [
                'start' => $start->format('Y-m-d H:i'),
                'end' => $end?->format('Y-m-d H:i'),
                'maintenance_reason' => $data['reason'],
                'end_note' => $open ? null : $data['end_note'],
            ];
            [$old, $new] = Correction::diff($this->snapshot($window), $values);
            if (! $new) {
                throw Correction::nothingChanged();
            }

            $startLog = MachineMaintenanceLog::lockForUpdate()->findOrFail($window['id']);
            // Detik dari waktu asli dipertahankan bila menitnya tidak berubah
            $startLog->fill(['logged_at' => isset($new['start']) ? $start : $startLog->logged_at, 'reason' => $data['reason']]);
            Correction::mark($startLog, $request, $reason);
            $startLog->save();

            if (! $open) {
                $endLog = MachineMaintenanceLog::lockForUpdate()->findOrFail($window['end_id']);
                $endLog->update(['logged_at' => isset($new['end']) ? $end : $endLog->logged_at, 'reason' => $values['end_note']]);
            } else {
                $machine->update(['maintenance_started_at' => $startLog->logged_at, 'maintenance_reason' => $data['reason']]);
            }

            $label = $this->label($window);
            Correction::audit($request, 'correct', $startLog, $label, $reason, $old, $new);

            return "Maintenance {$label} dikoreksi.";
        });
    }

    public function delete(Request $request, MachineMaintenanceLog $log, string $reason): string
    {
        return DB::transaction(function () use ($request, $log, $reason) {
            $machine = Machine::orderBy('id')->lockForUpdate()->firstOrFail();
            [$window] = $this->window($log);
            $open = $window['end_id'] === null;

            $startLog = MachineMaintenanceLog::findOrFail($window['id']);
            MachineMaintenanceLog::whereKey(array_filter([$window['id'], $window['end_id']]))->delete();

            if ($open) {
                $machine->update([
                    'status' => 'operating',
                    'maintenance_reason' => null,
                    'maintenance_started_at' => null,
                    'maintenance_expected_end_at' => null,
                    'maintenance_reported_by' => null,
                ]);
            }

            $label = $this->label($window);
            Correction::audit($request, 'delete', $startLog, $label, $reason, $this->snapshot($window));

            return "Maintenance {$label} dihapus." . ($open ? " Mesin {$machine->code} kembali berstatus beroperasi." : '');
        });
    }

    /**
     * Periode yang dimulai log ini, beserta periode sebelum dan sesudahnya.
     *
     * @return array{0: array, 1: ?array, 2: ?array}
     */
    private function window(MachineMaintenanceLog $log): array
    {
        $windows = MaintenanceHistory::windows()->values();
        $index = $windows->search(fn (array $w) => $w['id'] === $log->id);
        abort_if($index === false, 404, 'Periode maintenance tidak ditemukan.');

        return [$windows[$index], $windows[$index - 1] ?? null, $windows[$index + 1] ?? null];
    }

    private function ensureValid(Carbon $start, ?Carbon $end, bool $open, array $data, ?array $previous, ?array $next): void
    {
        $errors = [];
        if ($start->isFuture()) {
            $errors['start'] = 'Waktu mulai tidak boleh setelah sekarang.';
        } elseif ($previous && $previous['end'] && $start->lt($previous['end'])) {
            $errors['start'] = 'Waktu mulai bertumpuk dengan maintenance sebelumnya yang selesai ' . $this->time($previous['end']) . '.';
        }

        if (! $open) {
            if ($end->isFuture()) {
                $errors['end'] = 'Waktu selesai tidak boleh setelah sekarang.';
            } elseif ($end->lte($start)) {
                $errors['end'] = 'Waktu selesai harus setelah waktu mulai.';
            } elseif ($next && $end->gt($next['start'])) {
                $errors['end'] = 'Waktu selesai bertumpuk dengan maintenance berikutnya yang mulai ' . $this->time($next['start']) . '.';
            }
            if (! filled($data['end_note'] ?? null)) {
                $errors['end_note'] = 'Tulis catatan perbaikan, misalnya "Seal pompa diganti".';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** "reason" di audit dipakai untuk alasan koreksi, jadi alasan maintenance diberi nama lain. */
    private function snapshot(array $window): array
    {
        return [
            'start' => $window['start']->format('Y-m-d H:i'),
            'end' => $window['end']?->format('Y-m-d H:i'),
            'maintenance_reason' => $window['reason'],
            'end_note' => $window['end_note'],
        ];
    }

    private function label(array $window): string
    {
        return $this->time($window['start']);
    }

    private function time($at): string
    {
        return Carbon::parse($at)->locale('id')->translatedFormat('j M Y, H.i');
    }
}
