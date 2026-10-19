<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    /**
     * Mesin, shift, bahan, dan vendor awal. Versi pertama hanya satu mesin dan
     * satu bahan baku (PRD §4).
     */
    public function run(): void
    {
        $now = now();

        DB::table('machines')->updateOrInsert(
            ['code' => 'IRAC-01'],
            [
                'name' => 'Mesin Reclaimer IRAC',
                'description' => 'Mesin reclaimer distilasi pelarut packaging (Area Reclaimer)',
                'status' => 'operating',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $shifts = [
            ['code' => 'SHIFT-1', 'name' => 'Shift 1 (Pagi)', 'start_time' => '07:00:00', 'end_time' => '15:00:00'],
            ['code' => 'SHIFT-2', 'name' => 'Shift 2 (Siang)', 'start_time' => '15:00:00', 'end_time' => '23:00:00'],
            ['code' => 'SHIFT-3', 'name' => 'Shift 3 (Malam)', 'start_time' => '23:00:00', 'end_time' => '07:00:00'],
        ];
        foreach ($shifts as $shift) {
            DB::table('shifts')->updateOrInsert(['code' => $shift['code']], $shift);
        }

        $materials = [
            ['code' => 'TINTA-X', 'name' => 'Tinta X', 'type' => 'ink', 'unit' => 'kg'],
            ['code' => 'SOLVENT', 'name' => 'Solvent Pencuci', 'type' => 'solvent', 'unit' => 'kg'],
        ];
        foreach ($materials as $material) {
            DB::table('materials')->updateOrInsert(['code' => $material['code']], $material);
        }

        // Data contoh dari schema.sql lama. PIC dan kontak perlu diganti data asli dari HSE.
        $vendors = [
            [
                'name' => 'PT Prasadha Pamunah Limbah Industri (PPLI)',
                'pic_name' => 'Bpk. Hendra',
                'contact' => '021-8674060',
                'address' => 'Jl. Raya Narogong Km 22, Cileungsi, Bogor',
            ],
            [
                'name' => 'PT Wastec International',
                'pic_name' => 'Ibu Ratna',
                'contact' => '021-58302111',
                'address' => 'Kawasan Industri Cilegon, Banten',
            ],
        ];
        foreach ($vendors as $vendor) {
            DB::table('vendors')->updateOrInsert(
                ['name' => $vendor['name']],
                $vendor + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
