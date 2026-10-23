<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Toleransi persentase solvent naik ke 40% (permintaan lapangan, 23 Okt 2026).
     * Kunci parameter tetap target_recovery_rate.
     *
     * Semua rangkaian yang sudah selesai dinilai ulang dengan 40% (keputusan pengguna):
     * angka 35% dulu dibuat untuk rumus lama (Tinta X + solvent), jadi tidak cocok lagi
     * dengan rumus sekarang (solvent hasil ÷ Tinta X).
     */
    public function up(): void
    {
        $this->apply(40.0, 'Toleransi minimum persentase solvent');
    }

    public function down(): void
    {
        $this->apply(35.0, 'Target minimum recovery rate solvent');
    }

    private function apply(float $tolerance, string $description): void
    {
        DB::table('parameters')->where('param_key', 'target_recovery_rate')
            ->update(['param_value' => number_format($tolerance, 1, '.', ''), 'description' => $description, 'updated_at' => now()]);

        DB::table('reclaimer_batches')->whereNull('parent_batch_id')->whereNotNull('chain_completed_at')
            ->orderBy('id')->each(function ($root) use ($tolerance) {
                DB::table('reclaimer_batches')->where('id', $root->id)->orWhere('parent_batch_id', $root->id)->update([
                    'target_recovery_rate' => $tolerance,
                    'is_recovery_low' => (float) $root->chain_recovery_rate < $tolerance,
                ]);
            });
    }
};
