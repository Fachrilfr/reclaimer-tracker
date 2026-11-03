<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pengguna (Super Admin): tambah, ubah nama dan role, nonaktifkan, dan reset password.
 *
 * Tidak ada hapus: nama operator dan petugas tetap muncul di riwayat dan laporan lama.
 * NIK tidak bisa diubah karena dipakai login dan tercatat di audit. Super Admin tidak bisa
 * menonaktifkan atau menurunkan role dirinya sendiri, dan harus selalu ada satu Super Admin aktif.
 */
class UserController extends Controller
{
    private const ROLES = ['operator', 'supervisor', 'hse', 'management', 'admin'];

    public function index(): JsonResponse
    {
        // Terakhir aktif: token yang masih terpakai, atau login terakhir di audit log (token
        // dihapus saat logout, jadi audit log menjaga waktunya tetap ada)
        $tokenUse = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->selectRaw('tokenable_id as user_id, max(coalesce(last_used_at, created_at)) as at')
            ->groupBy('tokenable_id')
            ->pluck('at', 'user_id');
        $lastLogin = DB::table('audit_logs')
            ->where('action', 'login_success')
            ->selectRaw('user_id, max(created_at) as at')
            ->groupBy('user_id')
            ->pluck('at', 'user_id');
        $lastUsed = $lastLogin->all();
        foreach ($tokenUse as $id => $at) {
            $lastUsed[$id] = isset($lastUsed[$id]) ? max($lastUsed[$id], $at) : $at;
        }

        return response()->json([
            'status' => 'success',
            'data' => User::orderByDesc('is_active')->orderBy('role')->orderBy('nik')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'nik' => $u->nik,
                    'name' => $u->name,
                    'role' => $u->role,
                    'is_active' => $u->is_active,
                    'last_active_at' => isset($lastUsed[$u->id]) ? Carbon::parse($lastUsed[$u->id])->toIso8601String() : null,
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('users', 'nik')],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], $this->messages());

        $user = User::create([
            'nik' => strtoupper(trim($data['nik'])),
            'name' => trim($data['name']),
            'role' => $data['role'],
            'password' => $data['password'],
            'is_active' => true,
        ]);
        AuditLog::record($request, 'create', $user, $user->only(['nik', 'name', 'role']));

        return response()->json(['status' => 'success', 'message' => "Pengguna {$user->nik} ditambahkan."], 201);
    }

    /** Ubah nama, role, dan status aktif. NIK tetap. */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(self::ROLES)],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());
        $data['name'] = trim($data['name']);
        $data['is_active'] = (bool) $data['is_active'];

        $self = $request->user()->is($user);
        if ($self && $data['role'] !== $user->role) {
            throw ValidationException::withMessages(['role' => 'Role akun Anda sendiri tidak bisa diubah.']);
        }
        if ($self && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Akun Anda sendiri tidak bisa dinonaktifkan.']);
        }
        $leavesAdmin = $user->role === 'admin' && $user->is_active && ($data['role'] !== 'admin' || ! $data['is_active']);
        if ($leavesAdmin && User::where('role', 'admin')->where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['role' => 'Harus ada minimal satu Super Admin yang aktif.']);
        }

        $before = $user->only(array_keys($data));
        $user->update($data);
        // Akun nonaktif atau role berubah: semua sesi keluar, supaya hak akses lama tidak terbawa
        if (! $data['is_active'] || $before['role'] !== $data['role']) {
            $user->tokens()->delete();
        }
        AuditLog::record($request, 'update', $user, $user->only(array_keys($data)), $before);

        return response()->json(['status' => 'success', 'message' => "Pengguna {$user->nik} tersimpan."]);
    }

    /** Password sementara dari Super Admin; semua sesi pengguna itu keluar. */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:100']], $this->messages());

        $user->update(['password' => $data['password']]);
        if (! $request->user()->is($user)) {
            $user->tokens()->delete();
        }
        AuditLog::record($request, 'reset_password', $user, ['nik' => $user->nik]);

        return response()->json(['status' => 'success', 'message' => "Password {$user->nik} sudah diganti. Berikan password baru ke pengguna."]);
    }

    private function messages(): array
    {
        return [
            'nik.required' => 'Isi NIK.',
            'nik.min' => 'NIK paling sedikit 4 karakter.',
            'nik.max' => 'NIK paling panjang 30 karakter.',
            'nik.regex' => 'NIK hanya boleh huruf, angka, dan tanda hubung, misalnya OP-1002.',
            'nik.unique' => 'NIK ini sudah dipakai pengguna lain.',
            'name.required' => 'Isi nama.',
            'name.max' => 'Nama paling panjang 100 karakter.',
            'role.required' => 'Pilih role.',
            'role.in' => 'Role tidak dikenal.',
            'password.required' => 'Isi password.',
            'password.min' => 'Password paling sedikit 8 karakter.',
            'password.max' => 'Password paling panjang 100 karakter.',
        ];
    }
}
