<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data akun cukup NIK dan password (11 Nov 2026):
     * - NIK menjadi 8 digit angka. Akun bawaan dipetakan dengan digit pertama penanda role.
     * - Kolom email dihapus.
     * - Salinan password yang bisa dibuka Super Admin dihapus; login tetap memakai hash.
     */
    private const NIK = [
        'OP-1001' => '10000001',
        'OP-1002' => '10000002',
        'SPV-2001' => '20000001',
        'HSE-3001' => '30000001',
        'MGT-4001' => '40000001',
        'ADM-5001' => '50000001',
    ];

    private const ROLE_DIGIT = ['operator' => 1, 'supervisor' => 2, 'hse' => 3, 'management' => 4, 'admin' => 5];

    public function up(): void
    {
        // Termasuk akun yang sudah dihapus (lunak), supaya NIK lamanya tetap tidak bisa dipakai ulang
        $users = DB::table('users')->orderBy('id')->get(['id', 'nik', 'role']);
        $taken = $users->pluck('nik')->filter(fn ($nik) => preg_match('/^\d{8}$/', $nik))->values()->all();

        foreach ($users as $user) {
            if (preg_match('/^\d{8}$/', $user->nik)) {
                continue;
            }
            $nik = self::NIK[$user->nik] ?? null;
            if ($nik === null || in_array($nik, $taken, true)) {
                $nik = $this->nextFree($user->role, $taken);
            }
            DB::table('users')->where('id', $user->id)->update(['nik' => $nik]);
            $taken[] = $nik;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'password_encrypted']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 100)->nullable()->unique()->after('name');
            $table->text('password_encrypted')->nullable()->after('password');
        });

        foreach (self::NIK as $old => $new) {
            DB::table('users')->where('nik', $new)->update(['nik' => $old]);
        }
    }

    /** Akun di luar daftar bawaan: nomor berikutnya setelah NIK terbesar dengan digit role yang sama. */
    private function nextFree(string $role, array $taken): string
    {
        $digit = (string) (self::ROLE_DIGIT[$role] ?? 9);
        $max = collect($taken)->filter(fn ($nik) => str_starts_with($nik, $digit))->map(fn ($nik) => (int) $nik)->max();

        return (string) (($max ?? (int) $digit * 10_000_000) + 1);
    }
};
