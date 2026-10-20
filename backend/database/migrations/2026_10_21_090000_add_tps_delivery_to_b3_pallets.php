<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap pertama limbah B3: operator mengirim pallet dari area reclaimer ke TPS.
     * Batas kirim dihitung sejak pallet mulai terisi; pallet yang sudah penuh dianggap
     * dikirim ke TPS dan diserahkan ke HSE. Masa simpan 90 hari (TPS ke vendor) tetap
     * memakai expiry_date.
     */
    public function up(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->date('tps_due_date')->nullable()->after('expiry_date')->comment('Batas kirim ke TPS');
            $table->date('sent_to_tps_at')->nullable()->after('tps_due_date');
        });

        $now = now();
        DB::table('parameters')->insertOrIgnore([
            ['param_key' => 'tps_delivery_days', 'param_value' => '7', 'unit' => 'hari', 'description' => 'Batas kirim pallet dari reclaimer ke TPS, sejak pallet mulai terisi', 'created_at' => $now, 'updated_at' => $now],
            ['param_key' => 'tps_reminder_early_days', 'param_value' => '3', 'unit' => 'hari', 'description' => 'Peringatan perhatian, sisa waktu kirim ke TPS', 'created_at' => $now, 'updated_at' => $now],
            ['param_key' => 'tps_reminder_urgent_days', 'param_value' => '1', 'unit' => 'hari', 'description' => 'Peringatan mendesak, sisa waktu kirim ke TPS', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Data lama: batas kirim 7 hari dari mulai terisi; pallet penuh dianggap sudah dikirim
        foreach (DB::table('b3_pallets')->get() as $pallet) {
            DB::table('b3_pallets')->where('id', $pallet->id)->update([
                'tps_due_date' => date('Y-m-d', strtotime($pallet->start_date . ' +7 days')),
                'sent_to_tps_at' => $pallet->status === 'filling' ? null : ($pallet->full_date ?? $pallet->start_date),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->dropColumn(['tps_due_date', 'sent_to_tps_at']);
        });
        DB::table('parameters')->whereIn('param_key', ['tps_delivery_days', 'tps_reminder_early_days', 'tps_reminder_urgent_days'])->delete();
    }
};
