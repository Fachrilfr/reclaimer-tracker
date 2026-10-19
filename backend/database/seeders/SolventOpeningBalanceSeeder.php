<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SolventOpeningBalanceSeeder extends Seeder
{
    /**
     * Saldo awal solvent 1.000 kg sebagai titik awal buku besar. Angka contoh dari
     * schema.sql lama; ganti dengan saldo fisik saat aplikasi mulai dipakai.
     */
    public function run(): void
    {
        if (DB::table('solvent_ledger')->exists()) {
            return;
        }

        DB::table('solvent_ledger')->insert([
            'mutation_date' => now(),
            'type' => 'adjustment',
            'amount' => 1000,
            'previous_balance' => 0,
            'current_balance' => 1000,
            'reason' => 'Saldo awal sistem reclaimer',
            'user_id' => User::where('nik', 'ADM-5001')->value('id'),
            'created_at' => now(),
        ]);
    }
}
