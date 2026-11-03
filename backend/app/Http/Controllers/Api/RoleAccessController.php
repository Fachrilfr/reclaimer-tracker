<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hak akses per role (Super Admin): menu mana yang tampil dan apa yang boleh dilakukan.
 * Berlaku untuk semua akun dengan role itu, mulai halaman berikutnya yang mereka buka.
 */
class RoleAccessController extends Controller
{
    public function index(AccessMatrix $matrix): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => self::payload($matrix)]);
    }

    public function update(Request $request, string $role, AccessMatrix $matrix): JsonResponse
    {
        abort_unless(in_array($role, AccessMatrix::ROLES, true), 404);

        $rules = [];
        foreach (AccessMatrix::OPTIONS as $feature => $options) {
            $rules["levels.{$feature}"] = ['present', 'nullable', Rule::in($options)];
        }
        $levels = $request->validate($rules, ['*.in' => 'Tingkat akses ini tidak tersedia untuk menu tersebut.'])['levels'];

        // Super Admin tidak boleh mengunci diri dari halaman ini
        if ($role === 'admin' && ($levels['pengguna'] ?? null) !== 'kelola') {
            throw ValidationException::withMessages(['levels.pengguna' => 'Super Admin harus tetap bisa mengelola Pengguna.']);
        }

        $changed = $matrix->save($role, $levels, $request->user()->id);
        if ($changed) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'update_role_access',
                'entity_type' => 'role_permissions',
                'entity_id' => 0,
                'old_values' => array_map(fn ($c) => $c['from'], $changed) + ['role' => $role],
                'new_values' => array_map(fn ($c) => $c['to'], $changed) + ['role' => $role],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $changed
                ? count($changed) . ' hak akses diubah. Berlaku saat pengguna membuka halaman berikutnya.'
                : 'Tidak ada perubahan.',
            'data' => self::payload($matrix),
        ]);
    }

    private static function payload(AccessMatrix $matrix): array
    {
        return [
            'options' => AccessMatrix::OPTIONS,
            'roles' => collect(AccessMatrix::ROLES)->mapWithKeys(fn (string $role) => [$role => [
                'levels' => $matrix->forRole($role),
                'user_count' => User::where('role', $role)->count(),
            ]]),
        ];
    }
}
