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
        $this->as('50000001')->postJson('/api/users', ['nik' => '10000002', 'name' => 'Operator Budi', 'role' => 'operator', 'password' => 'rahasia123'])
            ->assertCreated();
        $this->assertDatabaseHas('users', ['nik' => '10000002', 'role' => 'operator', 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity_type' => 'users']);

        $this->postJson('/api/users', ['nik' => '10000002', 'name' => 'X', 'role' => 'operator', 'password' => 'rahasia123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nik']);
        $this->postJson('/api/users', ['nik' => 'OP 9', 'name' => 'X', 'role' => 'raja', 'password' => 'pendek'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nik', 'role', 'password']);
        // NIK hanya 8 digit angka
        foreach (['OP-10003', '1000003', '100000033', '1000000x', '1000 003'] as $nik) {
            $this->postJson('/api/users', ['nik' => $nik, 'name' => 'X', 'role' => 'operator', 'password' => 'rahasia123'])
                ->assertUnprocessable()->assertJsonPath('errors.nik.0', 'NIK harus 8 digit angka, misalnya 10000003.');
        }
        // Email tidak lagi disimpan walau dikirim
        $this->postJson('/api/users', ['nik' => '00000123', 'name' => 'Nol Depan', 'role' => 'hse', 'password' => 'rahasia123', 'email' => 'x@contoh.id'])
            ->assertCreated();
        $this->assertDatabaseHas('users', ['nik' => '00000123']);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => '10000002', 'password' => 'rahasia123'])->assertOk();
    }

    public function test_list_shows_nik_role_and_no_email(): void
    {
        $row = collect($this->as('50000001')->getJson('/api/users')->assertOk()->json('data'))->firstWhere('nik', '10000001');

        $this->assertSame('operator', $row['role']);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('password', $row);
    }

    public function test_deactivating_or_changing_role_logs_the_user_out(): void
    {
        $operator = User::where('nik', '10000001')->first();
        $operator->createToken('uji');

        $this->as('50000001')->putJson("/api/users/{$operator->id}", ['name' => $operator->name, 'role' => 'operator', 'is_active' => false])->assertOk();
        $this->assertFalse($operator->fresh()->is_active);
        $this->assertSame(0, $operator->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => '10000001', 'password' => 'password123'])->assertForbidden();
    }

    public function test_reset_password(): void
    {
        $operator = User::where('nik', '10000001')->first();
        $this->as('50000001')->postJson("/api/users/{$operator->id}/password", ['password' => 'baru12345'])->assertOk();

        $this->assertTrue(Hash::check('baru12345', $operator->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'reset_password', 'entity_id' => $operator->id]);
    }

    public function test_super_admin_cannot_see_passwords(): void
    {
        $this->as('50000001')->postJson('/api/users', ['nik' => '10000003', 'name' => 'Operator Citra', 'role' => 'operator', 'password' => 'citra2026'])->assertCreated();
        $user = User::where('nik', '10000003')->first();

        // Hanya hash yang tersimpan; tidak ada lagi jalan untuk membaca password
        $this->assertNotSame('citra2026', $user->getRawOriginal('password'));
        $this->getJson("/api/users/{$user->id}/password")->assertStatus(405);
        $this->assertArrayNotHasKey('password', $this->getJson('/api/users')->json('data.0'));

        $this->as('20000001')->postJson("/api/users/{$user->id}/password", ['password' => 'baru2026x'])->assertForbidden();
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = User::where('nik', '50000001')->first();
        $this->as('50000001');

        $this->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'role' => 'admin', 'is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
        $this->putJson("/api/users/{$admin->id}", ['name' => $admin->name, 'role' => 'hse', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);

        // Super Admin lain boleh diturunkan selama masih ada satu yang aktif
        $other = User::create(['nik' => '50000002', 'name' => 'Admin Dua', 'role' => 'admin', 'password' => 'password123', 'is_active' => true]);
        $this->putJson("/api/users/{$other->id}", ['name' => 'Admin Dua', 'role' => 'supervisor', 'is_active' => true])->assertOk();
    }

    public function test_delete_account_keeps_name_in_history(): void
    {
        $operator = User::where('nik', '10000001')->first();
        $operator->createToken('uji');
        $this->as('50000001')->deleteJson("/api/users/{$operator->id}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $operator->id]);
        $this->assertSame(0, $operator->tokens()->count());
        $this->assertNull(collect($this->getJson('/api/users')->json('data'))->firstWhere('nik', '10000001'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'delete', 'entity_type' => 'users', 'entity_id' => $operator->id]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['nik' => '10000001', 'password' => 'password123'])->assertUnauthorized();

        // Akun sendiri tidak bisa dihapus
        $admin = User::where('nik', '50000001')->first();
        $this->as('50000001')->deleteJson("/api/users/{$admin->id}")->assertUnprocessable()->assertJsonValidationErrors(['user']);
    }

    public function test_super_admin_changes_menus_and_actions_per_role(): void
    {
        $levels = $this->as('50000001')->getJson('/api/role-access')->assertOk()->json('data.roles.supervisor.levels');
        $this->assertSame('lihat', $levels['data_master']);
        $this->assertSame('lihat', $levels['surat_jalan']);

        // Supervisor boleh mengelola data master, dan menu Surat jalan dihilangkan
        $levels['data_master'] = 'kelola';
        $levels['surat_jalan'] = null;
        $this->putJson('/api/role-access/supervisor', ['levels' => $levels])->assertOk()
            ->assertJsonPath('message', '2 hak akses diubah. Berlaku saat pengguna membuka halaman berikutnya.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'update_role_access']);

        $this->as('20000001')->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('data.permissions.data_master', 'kelola')
            ->assertJsonMissingPath('data.permissions.surat_jalan');
        $this->postJson('/api/master/vendors', ['name' => 'PT Dari Supervisor'])->assertOk();
        $this->getJson('/api/surat-jalan/create')->assertForbidden();

        // Tingkat yang tidak tersedia ditolak, dan Super Admin tidak bisa mengunci dirinya
        $this->as('50000001')->putJson('/api/role-access/supervisor', ['levels' => ['laporan' => 'kelola'] + $levels])
            ->assertUnprocessable()->assertJsonValidationErrors(['levels.laporan']);
        $admin = $this->getJson('/api/role-access')->json('data.roles.admin.levels');
        $this->putJson('/api/role-access/admin', ['levels' => ['pengguna' => 'lihat'] + $admin])
            ->assertUnprocessable()->assertJsonValidationErrors(['levels.pengguna']);
    }

    public function test_home_falls_back_when_its_menu_is_removed(): void
    {
        $levels = $this->as('50000001')->getJson('/api/role-access')->json('data.roles.operator.levels');
        $levels['batch'] = null;
        $this->putJson('/api/role-access/operator', ['levels' => $levels])->assertOk();

        $this->as('10000001')->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.home', 'dashboard');
    }

    public function test_only_super_admin_manages_users(): void
    {
        foreach (['10000001', '20000001', '30000001', '40000001'] as $nik) {
            $this->as($nik)->getJson('/api/users')->assertForbidden();
        }
    }
}
