<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status proses batch dan penyelesaian rangkaian.
     *
     * Satu rangkaian = batch awal + semua batch lanjutannya (parent_batch_id menunjuk
     * batch awal). Rangkaian selesai saat batch terakhirnya disimpan berstatus
     * completed; saat itu recovery rate gabungan dan target saat itu disimpan di
     * batch awal, dan is_recovery_low diisi untuk seluruh rangkaian.
     */
    public function up(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->enum('process_status', ['in_progress', 'completed'])->default('completed')->after('parent_batch_id');
            // Diisi di batch awal saat rangkaian selesai
            $table->dateTime('chain_completed_at')->nullable()->after('process_status');
            $table->decimal('chain_recovery_rate', 5, 2)->nullable()->after('chain_completed_at');
            // Snapshot target recovery saat rangkaian selesai, diisi di semua batch rangkaian
            $table->decimal('target_recovery_rate', 5, 2)->nullable()->after('chain_recovery_rate');
        });

        // Data lama dianggap selesai: hitung recovery gabungan tiap rangkaian
        $target = DB::table('parameters')->where('param_key', 'target_recovery_rate')->value('param_value');

        DB::table('reclaimer_batches')->whereNull('parent_batch_id')->orderBy('id')->each(function ($root) use ($target) {
            $chain = DB::table('reclaimer_batches')->where('id', $root->id)->orWhere('parent_batch_id', $root->id);
            $input = (float) (clone $chain)->sum('total_input_weight');
            $recovery = (float) (clone $chain)->sum('recovery_weight');
            $rate = $input > 0 ? round($recovery / $input * 100, 2) : 0;

            (clone $chain)->update([
                'target_recovery_rate' => $target,
                'is_recovery_low' => $target !== null && $rate < (float) $target,
            ]);
            DB::table('reclaimer_batches')->where('id', $root->id)->update([
                'chain_completed_at' => (clone $chain)->max('created_at') ?? now(),
                'chain_recovery_rate' => $rate,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropColumn(['process_status', 'chain_completed_at', 'chain_recovery_rate', 'target_recovery_rate']);
        });
    }
};
