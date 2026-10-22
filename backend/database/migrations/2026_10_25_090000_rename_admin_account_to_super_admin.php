<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Role admin ditampilkan sebagai "Super Admin". Kunci role tetap `admin` (dipakai
     * hak akses dan data). Akun contoh dari seeder ikut diganti namanya, hanya bila
     * namanya belum pernah diubah.
     */
    public function up(): void
    {
        DB::table('users')->where('nik', 'ADM-5001')->where('name', 'Admin Sistem')->update(['name' => 'Super Admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('nik', 'ADM-5001')->where('name', 'Super Admin')->update(['name' => 'Admin Sistem']);
    }
};
