<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
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
    }
}
