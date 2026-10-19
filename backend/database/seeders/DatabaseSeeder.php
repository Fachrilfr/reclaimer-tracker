<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with default enterprise users.
     */
    public function run(): void
    {
        $users = [
            [
                'nik' => 'ADM-5001',
                'name' => 'Admin Sistem',
                'email' => 'admin@indofood.co.id',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'nik' => 'SPV-2001',
                'name' => 'Supervisor Reclaimer',
                'email' => 'supervisor@indofood.co.id',
                'password' => Hash::make('password123'),
                'role' => 'supervisor',
                'is_active' => true,
            ],
            [
                'nik' => 'OP-1001',
                'name' => 'Operator Aziz',
                'email' => 'aziz@indofood.co.id',
                'password' => Hash::make('password123'),
                'role' => 'operator',
                'is_active' => true,
            ],
            [
                'nik' => 'HSE-3001',
                'name' => 'HSE Officer',
                'email' => 'hse@indofood.co.id',
                'password' => Hash::make('password123'),
                'role' => 'hse',
                'is_active' => true,
            ],
            [
                'nik' => 'MGT-4001',
                'name' => 'Manajemen Divisi',
                'email' => 'manajemen@indofood.co.id',
                'password' => Hash::make('password123'),
                'role' => 'management',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['nik' => $userData['nik']],
                $userData
            );
        }
    }
}
