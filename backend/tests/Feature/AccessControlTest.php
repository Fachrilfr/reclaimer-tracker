<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route uji: belum ada modul yang memakai middleware akses.
        Route::middleware(['auth:sanctum', 'aktif', 'akses:batch,isi'])
            ->post('/api/_uji/batch', fn () => ['status' => 'success']);
        Route::middleware(['auth:sanctum', 'aktif', 'akses:batch'])
            ->get('/api/_uji/batch', fn () => ['status' => 'success']);
        Route::middleware(['auth:sanctum', 'aktif', 'akses:dashboard,tinjau'])
            ->post('/api/_uji/dashboard/tinjau', fn () => ['status' => 'success']);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('uji')->plainTextToken;
    }

    /**
     * Guard menyimpan user dari request sebelumnya dalam satu test,
     * jadi direset agar tiap request benar-benar memakai token-nya sendiri.
     */
    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_permissions_follow_prd_matrix(): void
    {
        $this->assertSame(
            ['dashboard' => 'lihat', 'batch' => 'isi', 'mesin' => 'isi', 'drum_b3' => 'isi'],
            User::factory()->role('operator')->make()->permissions(),
        );
        $this->assertSame(
            ['mesin' => 'lihat', 'laporan' => 'lihat', 'audit' => 'lihat', 'data_master' => 'kelola', 'pengguna' => 'kelola'],
            User::factory()->role('admin')->make()->permissions(),
        );
        $this->assertSame('tinjau', User::factory()->role('supervisor')->make()->accessLevel('dashboard'));
        $this->assertNull(User::factory()->role('management')->make()->accessLevel('batch'));
    }

    public function test_every_role_home_is_a_feature_it_can_open(): void
    {
        foreach (array_keys(config('access.home')) as $role) {
            $user = User::factory()->role($role)->make();
            $this->assertTrue($user->hasAccess($user->homeFeature()), "Role {$role} tidak bisa membuka halaman awalnya.");
        }
    }

    public function test_kelola_includes_isi_tinjau_and_lihat_but_lihat_does_not_include_isi(): void
    {
        $hse = User::factory()->role('hse')->make();
        $this->assertTrue($hse->hasAccess('drum_b3', 'isi'));
        $this->assertTrue($hse->hasAccess('drum_b3', 'tinjau'));
        $this->assertTrue($hse->hasAccess('drum_b3', 'lihat'));

        $supervisor = User::factory()->role('supervisor')->make();
        $this->assertTrue($supervisor->hasAccess('batch', 'lihat'));
        $this->assertFalse($supervisor->hasAccess('batch', 'isi'));
    }

    public function test_unknown_feature_or_level_is_a_programming_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::factory()->make()->hasAccess('fitur_tidak_ada');
    }

    public function test_unknown_level_is_a_programming_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::factory()->make()->hasAccess('batch', 'hapus');
    }

    public function test_middleware_allows_matching_level_and_blocks_others(): void
    {
        $operator = $this->tokenFor(User::factory()->role('operator')->create());
        $supervisor = $this->tokenFor(User::factory()->role('supervisor')->create());
        $management = $this->tokenFor(User::factory()->role('management')->create());

        $this->asToken($operator)->postJson('/api/_uji/batch')->assertOk();
        $this->asToken($supervisor)->getJson('/api/_uji/batch')->assertOk();

        $this->asToken($supervisor)->postJson('/api/_uji/batch')
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak punya akses untuk tindakan ini.');
        $this->asToken($management)->getJson('/api/_uji/batch')->assertForbidden();

        $this->asToken($supervisor)->postJson('/api/_uji/dashboard/tinjau')->assertOk();
        $this->asToken($operator)->postJson('/api/_uji/dashboard/tinjau')->assertForbidden();
    }

    public function test_request_without_token_gets_indonesian_401(): void
    {
        $this->getJson('/api/_uji/batch')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sesi Anda berakhir. Silakan masuk lagi.');
    }

    public function test_deactivated_user_loses_access_and_token_is_revoked(): void
    {
        $user = User::factory()->role('operator')->create();
        $token = $this->tokenFor($user);

        $user->update(['is_active' => false]);

        $this->asToken($token)->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Akun dinonaktifkan. Silakan hubungi Administrator.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
