<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParameterSeeder extends Seeder
{
    /**
     * Parameter awal PRD §8. Sebagian besar masih usulan dan menunggu konfirmasi
     * supervisor dan HSE.
     *
     * Hanya mengisi kunci yang belum ada, supaya nilai yang sudah diubah lewat
     * aplikasi tidak tertimpa saat seeder dijalankan ulang.
     */
    public function run(): void
    {
        $parameters = [
            ['param_key' => 'target_recovery_rate', 'param_value' => '40.0', 'unit' => '%', 'description' => 'Toleransi minimum persentase solvent'],
            ['param_key' => 'b3_max_storage_days', 'param_value' => '90', 'unit' => 'hari', 'description' => 'Batas masa simpan limbah B3 sejak pallet mulai terisi'],
            ['param_key' => 'b3_reminder_early_days', 'param_value' => '30', 'unit' => 'hari', 'description' => 'Pengingat awal, sisa masa simpan'],
            ['param_key' => 'b3_reminder_urgent_days', 'param_value' => '14', 'unit' => 'hari', 'description' => 'Pengingat mendesak, sisa masa simpan'],
            ['param_key' => 'b3_reminder_critical_days', 'param_value' => '7', 'unit' => 'hari', 'description' => 'Pengingat kritis, dikirim ulang tiap hari'],
            ['param_key' => 'min_pallet_per_delivery', 'param_value' => '1', 'unit' => 'pallet', 'description' => 'Minimal pallet per pengangkutan'],
            ['param_key' => 'drums_per_pallet', 'param_value' => '3', 'unit' => 'drum', 'description' => 'Isi satu pallet'],
            ['param_key' => 'max_vendor_delivery_weeks', 'param_value' => '3', 'unit' => 'minggu', 'description' => 'Batas penyerahan residu ke vendor'],
            ['param_key' => 'daily_reminder_hour', 'param_value' => '07:00', 'unit' => 'WIB', 'description' => 'Jam pengiriman pengingat harian ke HSE'],
            ['param_key' => 'tps_delivery_days', 'param_value' => '7', 'unit' => 'hari', 'description' => 'Batas kirim pallet dari reclaimer ke TPS, sejak pallet mulai terisi'],
            ['param_key' => 'tps_reminder_early_days', 'param_value' => '3', 'unit' => 'hari', 'description' => 'Peringatan perhatian, sisa waktu kirim ke TPS'],
            ['param_key' => 'tps_reminder_urgent_days', 'param_value' => '1', 'unit' => 'hari', 'description' => 'Peringatan mendesak, sisa waktu kirim ke TPS'],
        ];

        $now = now();
        foreach ($parameters as $parameter) {
            DB::table('parameters')->insertOrIgnore($parameter + ['created_at' => $now, 'updated_at' => $now]);
        }
    }
}
