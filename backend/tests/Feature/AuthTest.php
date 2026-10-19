<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_successful_with_valid_nik_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nik' => 'OP-1001',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'nik', 'name', 'role'],
                    'redirect',
                ],
            ]);

        $this->assertEquals('/batch/create', $response->json('data.redirect'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'nik' => 'OP-1001',
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

    public function test_me_and_logout_require_valid_token(): void
    {
        $token = $this->postJson('/api/auth/login', ['nik' => 'HSE-3001', 'password' => 'password123'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'hse');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
