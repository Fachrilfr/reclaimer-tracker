<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemakaian solvent dipisah dari batch.
     *
     * Solvent dipakai bukan bahan yang masuk ke mesin reclaimer, melainkan pengambilan
     * dari saldo hasil recovery. Jadi bahan masuk batch = Tinta X saja, residu =
     * Tinta X - recovery, dan recovery rate = recovery / Tinta X.
     *
     * Data lama: solvent dipakai yang tercatat di batch dipindah menjadi catatan
     * pemakaian solvent (mutasi saldonya tetap sama), lalu angka batch dan rangkaian
     * dihitung ulang dengan rumus baru. Target pembanding tetap snapshot saat
     * rangkaian selesai.
     */
    public function up(): void
    {
        Schema::create('solvent_usages', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('shift_id')->constrained();
            $table->decimal('weight', 10, 2);
            $table->string('purpose', 150)->comment('Keperluan pemakaian solvent');
            $table->string('notes', 500)->nullable();
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('used_by')->constrained('users');
            $table->timestamps();

            $table->index('date');
        });

        Schema::table('solvent_ledger', function (Blueprint $table) {
            $table->foreignId('solvent_usage_id')->nullable()->after('batch_id')->constrained('solvent_usages')->nullOnDelete();
        });

        $this->moveBatchSolventToUsages();
        $this->recalculateBatches();

        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropColumn('solvent_used_weight');
        });
    }

    private function moveBatchSolventToUsages(): void
    {
        DB::table('reclaimer_batches')->where('solvent_used_weight', '>', 0)->orderBy('id')->each(function ($batch) {
            $ledger = DB::table('solvent_ledger')->where('batch_id', $batch->id)->where('type', 'out')->first();

            $usageId = DB::table('solvent_usages')->insertGetId([
                'date' => $batch->date,
                'shift_id' => $batch->shift_id,
                'weight' => $batch->solvent_used_weight,
                'purpose' => "Tercatat di batch {$batch->batch_number}",
                'notes' => null,
                'balance_after' => $ledger->current_balance ?? $batch->solvent_balance_after,
                'used_by' => $batch->operator_id,
                'created_at' => $batch->created_at,
                'updated_at' => $batch->created_at,
            ]);

            if ($ledger) {
                DB::table('solvent_ledger')->where('id', $ledger->id)->update(['batch_id' => null, 'solvent_usage_id' => $usageId]);
            }
        });
    }

    private function recalculateBatches(): void
    {
        $rate = fn (float $part, float $whole) => $whole > 0 ? round($part / $whole * 100, 2) : 0;

        DB::table('reclaimer_batches')->whereNull('parent_batch_id')->orderBy('id')->each(function ($root) use ($rate) {
            $batches = DB::table('reclaimer_batches')->where('id', $root->id)->orWhere('parent_batch_id', $root->id)->orderBy('id')->get();
            $input = 0.0;
            $recovery = 0.0;

            foreach ($batches as $batch) {
                $input += (float) $batch->ink_weight;
                $recovery += (float) $batch->recovery_weight;
                DB::table('reclaimer_batches')->where('id', $batch->id)->update([
                    'total_input_weight' => $batch->ink_weight,
                    'residue_estimated_weight' => round($batch->ink_weight - $batch->recovery_weight, 2),
                    'recovery_rate_percent' => $rate($recovery, $input),
                    'residue_rate_percent' => $rate($input - $recovery, $input),
                ]);
            }

            if ($root->chain_completed_at === null) {
                return;
            }

            $chainRate = $rate($recovery, $input);
            DB::table('reclaimer_batches')->where('id', $root->id)->update(['chain_recovery_rate' => $chainRate]);
            DB::table('reclaimer_batches')->whereIn('id', $batches->pluck('id'))->update([
                'is_recovery_low' => $root->target_recovery_rate !== null && $chainRate < (float) $root->target_recovery_rate,
            ]);
        });
    }

    /** Kembali ke skema lama. Angka batch tidak dihitung ulang ke rumus lama. */
    public function down(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->decimal('solvent_used_weight', 10, 2)->default(0)->after('ink_weight');
        });
        Schema::table('solvent_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solvent_usage_id');
        });
        Schema::dropIfExists('solvent_usages');
    }
};
