<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi data awal. Aman dijalankan berulang: setiap seeder memakai updateOrCreate.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            ParameterSeeder::class,
            SolventOpeningBalanceSeeder::class,
        ]);
    }
}
