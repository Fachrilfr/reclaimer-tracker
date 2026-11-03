<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Batas kirim pallet ke TPS dihapus (3 Nov 2026): pallet penuh langsung tercatat di TPS,
     * dan satu-satunya batas waktu adalah masa simpan di TPS. Kolom tps_due_date dibiarkan
     * (nullable) supaya data lama tidak hilang, tapi tidak diisi atau dibaca lagi.
     */
    private const KEYS = ['tps_delivery_days', 'tps_reminder_early_days', 'tps_reminder_urgent_days'];

    public function up(): void
    {
        DB::table('parameters')->whereIn('param_key', self::KEYS)->delete();
    }

    public function down(): void
    {
        $now = now();
        DB::table('parameters')->insertOrIgnore([
            ['param_key' => 'tps_delivery_days', 'param_value' => '7', 'unit' => 'hari', 'description' => 'Batas kirim pallet dari reclaimer ke TPS, sejak pallet mulai terisi', 'created_at' => $now, 'updated_at' => $now],
            ['param_key' => 'tps_reminder_early_days', 'param_value' => '3', 'unit' => 'hari', 'description' => 'Peringatan perhatian, sisa waktu kirim ke TPS', 'created_at' => $now, 'updated_at' => $now],
            ['param_key' => 'tps_reminder_urgent_days', 'param_value' => '1', 'unit' => 'hari', 'description' => 'Peringatan mendesak, sisa waktu kirim ke TPS', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
