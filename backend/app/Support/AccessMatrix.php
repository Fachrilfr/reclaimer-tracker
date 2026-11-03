<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses per role: nilai awal dari config/access.php, ditimpa pengaturan Super Admin
 * di tabel role_permissions. Dimuat sekali per request (singleton di container).
 */
final class AccessMatrix
{
    /**
     * Tingkat yang masuk akal per fitur, urut dari terendah. Tingkat lain tidak dipakai
     * kode fitur itu, jadi tidak ditawarkan supaya pilihan di layar selalu berpengaruh.
     */
    public const OPTIONS = [
        'dashboard' => ['lihat', 'tinjau'],
        'dashboard_hse' => ['lihat'],
        'batch' => ['lihat', 'isi'],
        'solvent' => ['lihat', 'isi'],
        'mesin' => ['lihat', 'isi'],
        'limbah_b3' => ['lihat'],
        'drum_b3' => ['lihat', 'isi'],
        'surat_jalan' => ['lihat', 'isi'],
        'arsip' => ['lihat'],
        'laporan' => ['lihat'],
        'laporan_b3' => ['lihat'],
        'parameter' => ['lihat', 'kelola'],
        'data_master' => ['lihat', 'kelola'],
        'pengguna' => ['lihat', 'kelola'],
    ];

    public const ROLES = ['operator', 'supervisor', 'hse', 'management', 'admin'];

    /** @var array<string, array<string, ?string>> role => feature => level */
    private array $overrides = [];

    public function __construct()
    {
        // Sebelum migrasi dijalankan (misalnya saat install), cukup pakai config
        if (! Schema::hasTable('role_permissions')) {
            return;
        }
        foreach (DB::table('role_permissions')->get(['role', 'feature', 'level']) as $row) {
            $this->overrides[$row->role][$row->feature] = $row->level;
        }
    }

    public function level(string $role, string $feature): ?string
    {
        if (array_key_exists($feature, $this->overrides[$role] ?? [])) {
            return $this->overrides[$role][$feature];
        }

        return self::normalize($feature, config("access.features.{$feature}.{$role}"));
    }

    /** @return array<string, ?string> semua fitur untuk satu role, termasuk yang tanpa akses */
    public function forRole(string $role): array
    {
        return collect(array_keys(config('access.features')))
            ->mapWithKeys(fn (string $feature) => [$feature => $this->level($role, $feature)])
            ->all();
    }

    /** Simpan seluruh hak akses satu role; mengembalikan fitur yang berubah. */
    public function save(string $role, array $levels, int $userId): array
    {
        $changed = [];
        foreach ($levels as $feature => $level) {
            $old = $this->level($role, $feature);
            if ($old === $level) {
                continue;
            }
            DB::table('role_permissions')->updateOrInsert(
                ['role' => $role, 'feature' => $feature],
                ['level' => $level, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
            );
            $this->overrides[$role][$feature] = $level;
            $changed[$feature] = ['from' => $old, 'to' => $level];
        }

        return $changed;
    }

    /**
     * Nilai lama di config bisa berupa tingkat yang tidak ditawarkan (misalnya mesin
     * "kelola" untuk supervisor): dipetakan ke tingkat tertinggi yang ditawarkan.
     */
    private static function normalize(string $feature, ?string $level): ?string
    {
        if ($level === null) {
            return null;
        }
        $options = self::OPTIONS[$feature] ?? [$level];

        return in_array($level, $options, true) ? $level : end($options);
    }
}
