<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Handle user login via NIK and Password.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|min:4|max:30',
            'password' => 'required|string|min:4',
        ], [
            'nik.required' => 'NIK wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $nik = trim($request->nik);
        $user = User::where('nik', $nik)->first();

        // Cek kecocokan user, keaktifan akun, dan password
        if (!$user || !Hash::check($request->password, $user->password)) {
            // Catat log gagal login
            AuditLog::create([
                'user_id' => $user ? $user->id : null,
                'action' => 'login_failed',
                'entity_type' => 'users',
                'entity_id' => $user ? $user->id : 0,
                'new_values' => ['nik_attempted' => $nik],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'NIK atau kata sandi tidak valid.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun dinonaktifkan. Silakan hubungi Super Admin.',
            ], 403);
        }

        // Generate token Sanctum
        $token = $user->createToken('reclaimer_auth_token')->plainTextToken;

        // Catat log berhasil login
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login_success',
            'entity_type' => 'users',
            'entity_id' => $user->id,
            'new_values' => [
                'nik' => $user->nik,
                'role' => $user->role,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => ['token' => $token, ...$this->sessionPayload($user)],
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->sessionPayload($request->user()),
        ]);
    }

    /**
     * Data sesi yang dipakai frontend: profil, hak akses per fitur
     * (config/access.php), dan fitur yang dibuka pertama setelah login.
     */
    private function sessionPayload(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'nik' => $user->nik,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'permissions' => (object) $user->permissions(),
            'home' => $user->homeFeature(),
        ];
    }

    /**
     * Logout and revoke tokens.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'entity_type' => 'users',
                'entity_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil.',
        ]);
    }
}
