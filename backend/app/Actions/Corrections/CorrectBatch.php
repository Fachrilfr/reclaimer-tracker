<?php

namespace App\Actions\Corrections;

use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Support\BatchChain;
use App\Support\Correction;
use App\Support\MaterialBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi dan hapus input reclaimer.
 *
 * - Tinta X tidak bisa dikoreksi (tetap per input baru), begitu pula jenis input.
 * - Status selesai/belum selesai hanya bisa diubah di input terakhir sebuah proses:
 *   menjadi selesai menutup proses, menjadi belum selesai membuka proses lagi.
 * - Hanya input terakhir sebuah proses yang bisa dihapus.
 * - Residu dan persentase solvent semua input di proses itu dihitung ulang. Saldo
 *   solvent disesuaikan lewat baris penyesuaian; saldo yang tercatat di input lama tetap.
 */
class CorrectBatch
{
    /**
     * @param  array{date: string, shift_id: int, regu: ?string, process_status: string, recovery_weight: float,
     *               oil_temp: float, vapour_temp: float, notes: ?string}  $data
     */
    public function update(Request $request, ReclaimerBatch $batch, array $data, string $reason): string
    {
        return DB::transaction(function () use ($request, $batch, $data, $reason) {
            Correction::lockSolvent();
            $batch = ReclaimerBatch::lockForUpdate()->findOrFail($batch->id);
            $root = $batch->root();
            $members = BatchChain::of($root->fresh())->batches->values();
            $index = $members->search(fn (ReclaimerBatch $b) => $b->id === $batch->id);
            $last = $members->last();

            $this->ensureDateInOrder($data['date'], $members[$index - 1] ?? null, $members[$index + 1] ?? null);

            if ($data['process_status'] !== $batch->process_status && $last->id !== $batch->id) {
                throw ValidationException::withMessages([
                    'process_status' => "Status hanya bisa diubah di input terakhir proses ini ({$last->batch_number}).",
                ]);
            }

            $chainInput = round($members->sum('total_input_weight'), 2);
            $chainRecovery = round($members->sum('recovery_weight') - $batch->recovery_weight + $data['recovery_weight'], 2);
            if ($chainRecovery > $chainInput) {
                throw ValidationException::withMessages([
                    'recovery_weight' => 'Hasil solvent melebihi Tinta X proses (' . Correction::kg($chainInput) . ' kg). Periksa kembali angkanya.',
                ]);
            }

            [$old, $new] = Correction::diff($this->snapshot($batch), $this->snapshot($batch, $data));
            if (! $new) {
                throw Correction::nothingChanged();
            }

            $temperature = config('reclaimer.temperature');
            $delta = round($data['recovery_weight'] - $batch->recovery_weight, 2);

            $batch->fill($data + [
                'is_temp_abnormal' => $data['oil_temp'] < $temperature['oil']['min'] || $data['oil_temp'] > $temperature['oil']['max']
                    || $data['vapour_temp'] < $temperature['vapour']['min'] || $data['vapour_temp'] > $temperature['vapour']['max'],
                'updated_by' => $request->user()->id,
            ]);
            Correction::mark($batch, $request, $reason);
            $batch->save();

            $chainNote = $this->recalculate($root->fresh());

            $message = "Input {$batch->batch_number} dikoreksi." . $chainNote;
            if ($delta != 0) {
                $balance = Correction::adjustSolvent($request, $delta,
                    "Koreksi {$batch->batch_number}: hasil solvent " . Correction::kg($batch->recovery_weight - $delta) . ' → ' . Correction::kg($batch->recovery_weight) . ' kg',
                    ['batch_id' => $batch->id]);
                $new['solvent_adjustment'] = $delta;
                $message .= Correction::solventMessage($delta, $balance);
            }

            Correction::audit($request, 'correct', $batch, $batch->batch_number, $reason, $old, $new);

            return $message;
        });
    }

    public function delete(Request $request, ReclaimerBatch $batch, string $reason): string
    {
        return DB::transaction(function () use ($request, $batch, $reason) {
            Correction::lockSolvent();
            $batch = ReclaimerBatch::lockForUpdate()->findOrFail($batch->id);
            $root = $batch->root();
            $last = BatchChain::of($root->fresh())->last();

            if ($last->id !== $batch->id) {
                throw ValidationException::withMessages([
                    'reason' => "Input ini masih punya input lanjutan. Hapus dulu input terakhir proses ini ({$last->batch_number}).",
                ]);
            }

            $snapshot = $this->snapshot($batch) + ['ink_weight' => $batch->ink_weight, 'is_continuation' => $batch->is_continuation];
            $batch->forceFill(['updated_by' => $request->user()->id])->save();
            $batch->delete();

            $message = "Input {$batch->batch_number} dihapus.";
            if ($root->id !== $batch->id) {
                $message .= $this->recalculate($root->fresh());
            }
            if ($batch->recovery_weight > 0) {
                $balance = Correction::adjustSolvent($request, -$batch->recovery_weight,
                    "Hapus {$batch->batch_number}: hasil solvent " . Correction::kg($batch->recovery_weight) . ' kg dikeluarkan dari saldo',
                    ['batch_id' => $batch->id]);
                $message .= Correction::solventMessage(-$batch->recovery_weight, $balance);
            }

            Correction::audit($request, 'delete', $batch, $batch->batch_number, $reason, $snapshot);

            return $message;
        });
    }

    /** Nilai yang bisa dikoreksi, dengan shift berupa nama supaya riwayat koreksi mudah dibaca. */
    private function snapshot(ReclaimerBatch $batch, ?array $data = null): array
    {
        $data ??= [
            'date' => $batch->date->toDateString(),
            'shift_id' => $batch->shift_id,
            'regu' => $batch->regu,
            'process_status' => $batch->process_status,
            'recovery_weight' => $batch->recovery_weight,
            'oil_temp' => $batch->oil_temp,
            'vapour_temp' => $batch->vapour_temp,
            'notes' => $batch->notes,
        ];

        return [
            'date' => $data['date'],
            'shift' => Shift::whereKey($data['shift_id'])->value('name'),
            'regu' => $data['regu'],
            'process_status' => $data['process_status'],
            'recovery_weight' => $data['recovery_weight'],
            'oil_temp' => $data['oil_temp'],
            'vapour_temp' => $data['vapour_temp'],
            'notes' => $data['notes'],
        ];
    }

    private function ensureDateInOrder(string $date, ?ReclaimerBatch $previous, ?ReclaimerBatch $next): void
    {
        if ($previous && $date < $previous->date->toDateString()) {
            throw ValidationException::withMessages([
                'date' => "Tanggal tidak boleh sebelum input sebelumnya, {$previous->batch_number} (" . Correction::date($previous->date) . ').',
            ]);
        }
        if ($next && $date > $next->date->toDateString()) {
            throw ValidationException::withMessages([
                'date' => "Tanggal tidak boleh setelah input lanjutannya, {$next->batch_number} (" . Correction::date($next->date) . ').',
            ]);
        }
    }

    /**
     * Hitung ulang residu dan persentase solvent setiap input di proses, lalu status
     * prosesnya dari input terakhir. Proses yang selesai dibandingkan dengan toleransi
     * yang tersimpan saat selesai; yang baru selesai memakai toleransi saat ini.
     * Proses yang dibuka lagi kehilangan tinjauan supervisornya.
     *
     * @return string kalimat tambahan untuk pesan sukses bila status proses berubah
     */
    private function recalculate(ReclaimerBatch $root): string
    {
        $chain = BatchChain::of($root);
        $wasComplete = $root->chain_completed_at !== null;
        $input = 0.0;
        $recovery = 0.0;

        foreach ($chain->batches as $batch) {
            $balance = new MaterialBalance(ink: $batch->ink_weight, recovery: $batch->recovery_weight, priorInput: $input, priorRecovery: $recovery);
            $batch->update([
                'total_input_weight' => $balance->totalInput(),
                'residue_estimated_weight' => $balance->residue(),
                'recovery_rate_percent' => $balance->recoveryRate(),
                'residue_rate_percent' => $balance->residueRate(),
            ]);
            $input = $balance->chainInput();
            $recovery = $balance->chainRecovery();
        }

        $ids = $chain->batches->pluck('id');
        if ($chain->last()->process_status === 'completed') {
            $rate = $chain->rate();
            $target = $root->target_recovery_rate ?? Parameter::number('target_recovery_rate');
            ReclaimerBatch::whereIn('id', $ids)->update(['is_recovery_low' => $rate < $target, 'target_recovery_rate' => $target]);
            $root->update(['chain_completed_at' => $root->chain_completed_at ?? now(), 'chain_recovery_rate' => $rate]);

            return $wasComplete ? '' : " Proses {$root->batch_number} sekarang selesai.";
        }

        ReclaimerBatch::whereIn('id', $ids)->update(['is_recovery_low' => false, 'target_recovery_rate' => null]);
        $root->update([
            'chain_completed_at' => null,
            'chain_recovery_rate' => null,
            'supervisor_reviewed' => false,
            'supervisor_notes' => null,
            'supervisor_reviewed_by' => null,
            'supervisor_reviewed_at' => null,
        ]);

        return $wasComplete ? " Proses {$root->batch_number} dibuka lagi dan menunggu input lanjutan." : '';
    }
}
