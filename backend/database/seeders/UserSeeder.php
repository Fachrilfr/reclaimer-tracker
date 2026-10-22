<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Satu akun per role. Kata sandi semua akun: password123.
     */
    public function run(): void
    {
        $users = [
            ['nik' => 'ADM-5001', 'name' => 'Super Admin', 'email' => 'admin@indofood.co.id', 'role' => 'admin'],
            ['nik' => 'SPV-2001', 'name' => 'Supervisor Reclaimer', 'email' => 'supervisor@indofood.co.id', 'role' => 'supervisor'],
            ['nik' => 'OP-1001', 'name' => 'Operator Aziz', 'email' => 'aziz@indofood.co.id', 'role' => 'operator'],
            ['nik' => 'HSE-3001', 'name' => 'HSE Officer', 'email' => 'hse@indofood.co.id', 'role' => 'hse'],
            ['nik' => 'MGT-4001', 'name' => 'Manajemen Divisi', 'email' => 'manajemen@indofood.co.id', 'role' => 'management'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['nik' => $user['nik']],
                $user + ['password' => Hash::make('password123'), 'is_active' => true],
            );
        }
    }
}
