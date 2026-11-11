<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // Bukan $seed = true: RefreshDatabase hanya menjalankan seeder saat
    // migrasi pertama di proses test, jadi hasilnya bergantung urutan kelas.
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_login_successful_with_valid_nik_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nik' => '10000001',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'nik', 'name', 'role'],
                    'permissions',
                    'home',
                ],
            ]);

        $response->assertJsonPath('data.home', 'batch')
            ->assertJsonPath('data.permissions.batch', 'isi')
            ->assertJsonMissingPath('data.permissions.parameter');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nik' => '10000001',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'NIK atau kata sandi tidak valid.',
            ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_login_rejected_for_inactive_account(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'password123']);

        $this->postJson('/api/auth/login', ['nik' => $user->nik, 'password' => 'password123'])
            ->assertStatus(403);
    }

    public function test_login_nik_must_be_8_digits(): void
    {
        foreach (['OP-1001', '1000001', '100000011', '1000000a'] as $nik) {
            $this->postJson('/api/auth/login', ['nik' => $nik, 'password' => 'password123'])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'NIK berupa 8 digit angka.');
        }
    }

    public function test_every_role_changes_own_password_and_all_sessions_log_out(): void
    {
        foreach (['10000001', '20000001', '30000001', '40000001', '50000001'] as $nik) {
            $user = User::where('nik', $nik)->first();
            $phone = $user->createToken('hp')->plainTextToken;
            $laptop = $user->createToken('laptop')->plainTextToken;

            $this->app['auth']->forgetGuards();
            $this->withToken($laptop)->postJson('/api/auth/password', [
                'current_password' => 'password123', 'password' => 'baru-2026', 'password_confirmation' => 'baru-2026',
            ])->assertOk()->assertJsonPath('message', 'Password sudah diganti. Masuk lagi dengan password baru.');

            // Semua sesi keluar, termasuk yang dipakai untuk mengganti: pengguna masuk lagi untuk mencoba
            foreach ([$phone, $laptop] as $token) {
                $this->app['auth']->forgetGuards();
                $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
            }
            $this->assertTrue(Hash::check('baru-2026', $user->fresh()->password));
        }
        $this->assertSame(5, AuditLog::where('action', 'change_password')->count());

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => '10000001', 'password' => 'password123'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['nik' => '10000001', 'password' => 'baru-2026'])->assertOk();
    }

    public function test_change_password_validation(): void
    {
        $this->actingAs(User::where('nik', '10000001')->first(), 'sanctum');

        $this->postJson('/api/auth/password', ['current_password' => 'salah123', 'password' => 'baru-2026', 'password_confirmation' => 'baru-2026'])
            ->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'Password saat ini salah.');
        $this->postJson('/api/auth/password', ['current_password' => 'password123', 'password' => 'pendek', 'password_confirmation' => 'beda'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password', 'password_confirmation']);
        $this->postJson('/api/auth/password', ['current_password' => 'password123', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', 'Password baru harus berbeda dari password saat ini.');

        $this->assertTrue(Hash::check('password123', User::where('nik', '10000001')->value('password')));
    }

    public function test_me_and_logout_require_valid_token(): void
    {
        $token = $this->postJson('/api/auth/login', ['nik' => '30000001', 'password' => 'password123'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'hse')
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonPath('data.permissions.data_master', 'kelola');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
