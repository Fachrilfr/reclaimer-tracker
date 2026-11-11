<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Satu akun per role. NIK 8 digit, digit pertama menandai role. Kata sandi semua akun: password123.
     */
    public function run(): void
    {
        $users = [
            ['nik' => '50000001', 'name' => 'Super Admin', 'role' => 'admin'],
            ['nik' => '20000001', 'name' => 'Supervisor Reclaimer', 'role' => 'supervisor'],
            ['nik' => '10000001', 'name' => 'Operator Aziz', 'role' => 'operator'],
            ['nik' => '30000001', 'name' => 'HSE Officer', 'role' => 'hse'],
            ['nik' => '40000001', 'name' => 'Manajemen Divisi', 'role' => 'management'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['nik' => $user['nik']],
                $user + ['password' => Hash::make('password123'), 'is_active' => true],
            );
        }
    }
}
