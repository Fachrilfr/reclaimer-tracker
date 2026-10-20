<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\SolventLedger;
use App\Support\MaterialBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan batch reclaimer beserta mutasi saldo solvent (FR-01 s/d FR-06, FR-10).
 */
class RecordBatch
{
    /**
     * @param  array{date: string, shift_id: int, regu: ?string, is_continuation: bool, parent_batch_id: ?int,
     *               ink_weight: float, solvent_used_weight: float, recovery_weight: float,
     *               oil_temp: float, vapour_temp: float, notes: ?string}  $data
     */
    public function handle(Request $request, array $data): ReclaimerBatch
    {
        return DB::transaction(function () use ($request, $data) {
            // Kunci saldo (PRD §9): baris bahan SOLVENT dipakai sebagai kunci, supaya dua
            // operator yang menyimpan bersamaan tidak membaca saldo yang sama lalu saling menimpa.
            Material::where('code', 'SOLVENT')->lockForUpdate()->firstOrFail();
            $balanceBefore = SolventLedger::currentBalance(lock: true);

            [$root, $priorInput, $priorRecovery] = $this->chainBefore($data);

            $balance = new MaterialBalance(
                ink: $data['ink_weight'],
                solventUsed: $data['solvent_used_weight'],
                recovery: $data['recovery_weight'],
                priorInput: $priorInput,
                priorRecovery: $priorRecovery,
            );

            $this->ensureValid($balance, $balanceBefore, $root !== null);

            $temperature = config('reclaimer.temperature');
            $isTempAbnormal = $data['oil_temp'] < $temperature['oil']['min'] || $data['oil_temp'] > $temperature['oil']['max']
                || $data['vapour_temp'] < $temperature['vapour']['min'] || $data['vapour_temp'] > $temperature['vapour']['max'];

            $balanceAfter = $balance->solventBalanceAfter($balanceBefore);

            $batch = ReclaimerBatch::create([
                'batch_number' => $this->nextBatchNumber($data['date']),
                'date' => $data['date'],
                'shift_id' => $data['shift_id'],
                'regu' => $data['regu'] ?? null,
                'operator_id' => $request->user()->id,
                'machine_id' => Machine::orderBy('id')->value('id'),
                'material_id' => Material::where('type', 'ink')->where('is_active', true)->orderBy('id')->value('id'),
                'is_continuation' => $root !== null,
                'parent_batch_id' => $root?->id,
                'ink_weight' => $data['ink_weight'],
                'solvent_used_weight' => $data['solvent_used_weight'],
                'recovery_weight' => $data['recovery_weight'],
                'oil_temp' => $data['oil_temp'],
                'vapour_temp' => $data['vapour_temp'],
                'is_temp_abnormal' => $isTempAbnormal,
                'total_input_weight' => $balance->totalInput(),
                'residue_estimated_weight' => $balance->residue(),
                // Untuk batch lanjutan, persentase ini gabungan seluruh rangkaian (FR-05)
                'recovery_rate_percent' => $balance->recoveryRate(),
                'residue_rate_percent' => $balance->residueRate(),
                'solvent_balance_after' => $balanceAfter,
                'is_recovery_low' => $balance->recoveryRate() < Parameter::number('target_recovery_rate'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->writeLedger($batch, $request, $balanceBefore, $balance);

            AuditLog::record($request, 'create', $batch, $batch->only([
                'batch_number', 'date', 'shift_id', 'is_continuation', 'parent_batch_id', 'ink_weight',
                'solvent_used_weight', 'recovery_weight', 'oil_temp', 'vapour_temp', 'solvent_balance_after',
            ]));

            return $batch;
        });
    }

    /**
     * Batch awal rangkaian dan total input serta recovery yang sudah tercatat di rangkaian itu.
     *
     * @return array{0: ?ReclaimerBatch, 1: float, 2: float}
     */
    private function chainBefore(array $data): array
    {
        if (! $data['is_continuation']) {
            return [null, 0.0, 0.0];
        }

        $picked = ReclaimerBatch::findOrFail($data['parent_batch_id']);
        // Lanjutan dari batch lanjutan tetap disambungkan ke batch awalnya
        $root = $picked->is_continuation ? $picked->parent : $picked;

        $chain = ReclaimerBatch::where('id', $root->id)->orWhere('parent_batch_id', $root->id);

        return [$root, (float) $chain->sum('total_input_weight'), (float) $chain->sum('recovery_weight')];
    }

    private function ensureValid(MaterialBalance $balance, float $balanceBefore, bool $isContinuation): void
    {
        $errors = [];

        if ($balance->recoveryExceedsInput()) {
            $errors['recovery_weight'] = $isContinuation
                ? sprintf(
                    'Hasil recovery melebihi bahan masuk rangkaian batch (%s kg). Periksa kembali angka recovery.',
                    number_format($balance->chainInput(), 2, ',', '.'),
                )
                : 'Hasil recovery melebihi bahan masuk (Tinta X + solvent). Periksa kembali angka recovery.';
        }

        if ($balance->solventUsed > $balanceBefore) {
            $errors['solvent_used_weight'] = sprintf(
                'Solvent dipakai melebihi saldo penyimpanan (%s kg). Periksa kembali angka solvent.',
                number_format($balanceBefore, 2, ',', '.'),
            );
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Nomor batch otomatis per tanggal, contoh BR-20261020-001 (FR-01). Aman karena dipanggil di dalam kunci saldo. */
    private function nextBatchNumber(string $date): string
    {
        $prefix = 'BR-' . Carbon::parse($date)->format('Ymd') . '-';
        $last = ReclaimerBatch::withTrashed()->where('batch_number', 'like', $prefix . '%')->max('batch_number');
        $sequence = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /** Buku besar mutasi solvent (FR-10): keluar sebesar solvent dipakai, masuk sebesar hasil recovery. */
    private function writeLedger(ReclaimerBatch $batch, Request $request, float $balance, MaterialBalance $material): void
    {
        $mutations = [
            ['out', $material->solventUsed, -$material->solventUsed],
            ['in', $material->recovery, $material->recovery],
        ];

        foreach ($mutations as [$type, $amount, $delta]) {
            if ($amount <= 0) {
                continue;
            }

            SolventLedger::create([
                'mutation_date' => now(),
                'type' => $type,
                'amount' => $amount,
                'previous_balance' => $balance,
                'current_balance' => $balance = round($balance + $delta, 2),
                'batch_id' => $batch->id,
                'user_id' => $request->user()->id,
                'created_at' => now(),
            ]);
        }
    }
}
