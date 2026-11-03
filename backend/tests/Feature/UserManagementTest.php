<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman Pengguna (Super Admin): tambah, ubah, nonaktifkan, reset password, dan
 * pengaman supaya Super Admin tidak mengunci dirinya sendiri.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $nik): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::where('nik', $nik)->first(), 'sanctum');
    }

    public function test_admin_adds_user_who_can_log_in(): void
    {
        $this->as('ADM-5001')->postJson('/api/users', ['nik' => 'op-1002', 'name' => 'Operator Budi', 'role' => 'operator', 'password' => 'rahasia123'])
            ->assertCreated();
        $this->assertDatabaseHas('users', ['nik' => 'OP-1002', 'role' => 'operator', 'is_active' => true, 'email' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity_type' => 'users']);

        $this->postJson('/api/users', ['nik' => 'OP-1002', 'name' => 'X', 'role' => 'operator', 'password' => 'rahasia123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nik']);
        $this->postJson('/api/users', ['nik' => 'OP 9', 'name' => 'X', 'role' => 'raja', 'password' => 'pendek'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nik', 'role', 'password']);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => 'OP-1002', 'password' => 'rahasia123'])->assertOk();
    }

    public function test_list_shows_nik_role_and_no_email(): void
    {
        $row = collect($this->as('ADM-5001')->getJson('/api/users')->assertOk()->json('data'))->firstWhere('nik', 'OP-1001');

        $this->assertSame('operator', $row['role']);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('password', $row);
    }

    public function test_deactivating_or_changing_role_logs_the_user_out(): void
    {
        $operator = User::where('nik', 'OP-1001')->first();
        $operator->createToken('uji');

        $this->as('ADM-5001')->putJson("/api/users/{$operator->id}", ['name' => $operator->name, 'role' => 'operator', 'is_active' => false])->assertOk();
        $this->assertFalse($operator->fresh()->is_active);
        $this->assertSame(0, $operator->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => 'OP-1001', 'password' => 'password123'])->assertForbidden();
    }

    public function test_reset_password(): void
    {
        $operator = User::where('nik', 'OP-1001')->first();
        $this->as('ADM-5001')->postJson("/api/users/{$operator->id}/password", ['password' => 'baru12345'])->assertOk();

        $this->assertTrue(Hash::check('baru12345', $operator->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'reset_password', 'entity_id' => $operator->id]);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = User::where('nik', 'ADM-5001')->first();
        $this->as('ADM-5001');

        $this->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'role' => 'admin', 'is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
        $this->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'role' => 'hse', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);

        // Super Admin lain boleh diturunkan selama masih ada satu yang aktif
        $other = User::create(['nik' => 'ADM-5002', 'name' => 'Admin Dua', 'role' => 'admin', 'password' => 'password123', 'is_active' => true]);
        $this->putJson("/api/users/{$other->id}", ['name' => 'Admin Dua', 'role' => 'supervisor', 'is_active' => true])->assertOk();
    }

    public function test_only_super_admin_manages_users(): void
    {
        foreach (['OP-1001', 'SPV-2001', 'HSE-3001', 'MGT-4001'] as $nik) {
            $this->as($nik)->getJson('/api/users')->assertForbidden();
        }
    }
}
