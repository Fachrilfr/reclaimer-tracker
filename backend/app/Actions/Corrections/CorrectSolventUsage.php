<?php

namespace App\Actions\Corrections;

use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use App\Support\Correction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi dan hapus pemakaian solvent. Selisih berat kembali ke atau diambil dari saldo
 * lewat baris penyesuaian; saldo setelah pemakaian yang tercatat di baris lama tetap.
 */
class CorrectSolventUsage
{
    /** @param  array{date: string, shift_id: int, weight: float, purpose: string, notes: ?string}  $data */
    public function update(Request $request, SolventUsage $usage, array $data, string $reason): string
    {
        return DB::transaction(function () use ($request, $usage, $data, $reason) {
            Correction::lockSolvent();
            $usage = SolventUsage::lockForUpdate()->findOrFail($usage->id);

            [$old, $new] = Correction::diff($this->snapshot($usage), $this->snapshot($usage, $data));
            if (! $new) {
                throw Correction::nothingChanged();
            }

            // Pemakaian bertambah sama dengan mengambil solvent lagi: tidak boleh melebihi saldo
            $delta = round($usage->weight - $data['weight'], 2);
            $balance = SolventLedger::currentBalance(lock: true);
            if ($delta < 0 && $balance + $delta < 0) {
                throw ValidationException::withMessages([
                    'weight' => 'Tambahan pemakaian ' . Correction::kg(-$delta) . ' kg melebihi saldo solvent (' . Correction::kg($balance) . ' kg).',
                ]);
            }

            $label = $this->label($usage);
            $usage->fill($data);
            Correction::mark($usage, $request, $reason);
            $usage->save();

            $message = "Pemakaian solvent {$label} dikoreksi.";
            if ($delta != 0) {
                $balance = Correction::adjustSolvent($request, $delta,
                    "Koreksi pemakaian solvent {$label}: " . Correction::kg($usage->weight + $delta) . ' → ' . Correction::kg($usage->weight) . ' kg',
                    ['solvent_usage_id' => $usage->id]);
                $new['solvent_adjustment'] = $delta;
                $message .= Correction::solventMessage($delta, $balance);
            }

            Correction::audit($request, 'correct', $usage, $label, $reason, $old, $new);

            return $message;
        });
    }

    public function delete(Request $request, SolventUsage $usage, string $reason): string
    {
        return DB::transaction(function () use ($request, $usage, $reason) {
            Correction::lockSolvent();
            $usage = SolventUsage::lockForUpdate()->findOrFail($usage->id);
            $label = $this->label($usage);
            $snapshot = $this->snapshot($usage);

            $usage->delete();
            $balance = Correction::adjustSolvent($request, $usage->weight,
                "Hapus pemakaian solvent {$label}: " . Correction::kg($usage->weight) . ' kg kembali ke saldo',
                ['solvent_usage_id' => $usage->id]);

            Correction::audit($request, 'delete', $usage, $label, $reason, $snapshot);

            return "Pemakaian solvent {$label} dihapus." . Correction::solventMessage($usage->weight, $balance);
        });
    }

    private function label(SolventUsage $usage): string
    {
        return Correction::date($usage->date) . ' (' . Correction::kg($usage->weight) . ' kg)';
    }

    private function snapshot(SolventUsage $usage, ?array $data = null): array
    {
        $data ??= [
            'date' => $usage->date->toDateString(),
            'shift_id' => $usage->shift_id,
            'weight' => $usage->weight,
            'purpose' => $usage->purpose,
            'notes' => $usage->notes,
        ];

        return [
            'date' => $data['date'],
            'shift' => Shift::whereKey($data['shift_id'])->value('name'),
            'weight' => $data['weight'],
            'purpose' => $data['purpose'],
            'notes' => $data['notes'],
        ];
    }
}
