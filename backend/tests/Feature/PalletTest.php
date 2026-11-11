<?php

namespace Tests\Feature;

use App\Models\B3Pallet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalletTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->operator = User::where('nik', '10000001')->first();
        $this->actingAs($this->operator, 'sanctum');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operator_starts_and_fills_pallet_in_real_time(): void
    {
        // Tombol "mulai diisi" ditekan 1 Okt pukul 08.15: tanggal dan jam diambil saat itu
        Carbon::setTestNow('2026-10-01 08:15:00');
        $pallet = $this->postJson('/api/pallets', ['notes' => 'Pallet kayu baru'])
            ->assertCreated()
            ->assertJsonPath('data.pallet_number', 'PLT-202610-001')
            ->assertJsonPath('data.start_date', '2026-10-01')
            ->assertJsonPath('data.status', 'filling')
            ->assertJsonPath('data.notes', 'Pallet kayu baru')
            // Masih di area reclaimer: masa simpan belum berjalan
            ->assertJsonPath('data.expiry_date', null)
            ->assertJsonPath('data.storage.level', 'reclaimer')
            ->assertJsonPath('data.storage.days_remaining', null)
            ->json('data');
        $this->assertStringStartsWith('2026-10-01T08:15:00', $pallet['started_at']);

        // Tombol "pallet penuh" ditekan 5 Okt pukul 14.40: langsung terkirim ke TPS
        Carbon::setTestNow('2026-10-05 14:40:00');
        $full = $this->postJson("/api/pallets/{$pallet['id']}/full", ['notes' => 'Tutup drum diganti'])
            ->assertOk()
            ->assertJsonPath('message', 'Pallet PLT-202610-001 penuh dan terkirim ke TPS.')
            ->assertJsonPath('data.status', 'full')
            ->assertJsonPath('data.full_date', '2026-10-05')
            ->assertJsonPath('data.drum_count', 3)
            // Lama pengisian: 1 Okt sampai 5 Okt = 4 hari
            ->assertJsonPath('data.fill_days', 4)
            ->assertJsonPath('data.sent_to_tps_at', '2026-10-05')
            ->assertJsonPath('data.notes', "Pallet kayu baru\nTutup drum diganti")
            // Masa simpan mulai dihitung saat sampai di TPS: 5 Okt + 90 hari = 3 Jan
            ->assertJsonPath('data.expiry_date', '2027-01-03')
            ->assertJsonPath('data.storage.days_remaining', 90)
            ->json('data');
        $this->assertStringStartsWith('2026-10-05T14:40:00', $full['full_at']);

        // Tidak bisa ditandai penuh dua kali; drum tidak dicatat satu per satu lagi
        $this->postJson("/api/pallets/{$pallet['id']}/full")->assertUnprocessable()->assertJsonValidationErrors(['pallet']);
        $this->assertDatabaseCount('b3_drum_logs', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'entity_type' => 'b3_pallets']);
        $this->getJson('/api/pallets')->assertOk()->assertJsonPath('data.filling', null);
    }

    public function test_pallet_has_no_tps_deadline_only_storage_limit(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');
        $pallet = $this->postJson('/api/pallets')->assertCreated()->json('data');
        $this->assertArrayNotHasKey('tps', $pallet);
        $this->assertArrayNotHasKey('tps_due_date', $pallet);
        $this->assertNull(B3Pallet::find($pallet['id'])->tps_due_date);

        // Berapa lama pun diisi, tidak ada peringatan kirim ke TPS; masa simpan 90 hari sejak sampai di TPS
        Carbon::setTestNow('2026-10-24 11:00:00');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonMissingPath('data.tps_pallets');
        $this->postJson("/api/pallets/{$pallet['id']}/full")->assertOk()
            ->assertJsonPath('data.drum_count', 3)
            ->assertJsonPath('data.expiry_date', '2027-01-22');
    }

    public function test_only_one_pallet_can_be_filling(): void
    {
        $this->postJson('/api/pallets')->assertCreated();

        $this->postJson('/api/pallets')
            ->assertUnprocessable()
            ->assertJsonPath('errors.pallet.0', 'Pallet PLT-202610-001 masih diisi. Tandai pallet itu penuh sebelum memulai pallet baru.');
    }

    public function test_storage_level_follows_reminder_thresholds(): void
    {
        $cases = [
            '2026-08-21' => [30, 'perhatian'],  // kedaluwarsa 19 Nov
            '2026-08-05' => [14, 'mendesak'],
            '2026-07-29' => [7, 'kritis'],
            '2026-07-19' => [-3, 'kritis'],     // lewat 3 hari
            '2026-08-22' => [31, 'aman'],
        ];

        // Tanggal kunci = tanggal sampai di TPS
        foreach ($cases as $arrived => [$days, $level]) {
            $pallet = B3Pallet::create([
                'pallet_number' => "PLT-UJI-{$arrived}",
                'start_date' => Carbon::parse($arrived)->subDays(5),
                'max_storage_days' => 90,
                'sent_to_tps_at' => $arrived,
                'expiry_date' => Carbon::parse($arrived)->addDays(90),
                'status' => 'full',
            ]);

            $status = $pallet->storageStatus();
            $this->assertSame($days, $status->daysRemaining, "di TPS {$arrived}");
            $this->assertSame($level, $status->level, "di TPS {$arrived}");
        }

        // Pallet di area reclaimer belum terhitung masa simpannya
        $filling = B3Pallet::create([
            'pallet_number' => 'PLT-UJI-ISI', 'start_date' => '2026-06-01', 'max_storage_days' => 90, 'status' => 'filling',
        ]);
        $this->assertSame('reclaimer', $filling->storageStatus()->level);
        $this->assertNull($filling->storageStatus()->daysRemaining);
        $this->assertFalse($filling->storageStatus()->needsAction());
    }

    public function test_access_follows_matrix(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', '20000001')->first(), 'sanctum');
        $this->getJson('/api/pallets')->assertOk();
        $this->postJson('/api/pallets')->assertForbidden();

        $this->app['auth']->forgetGuards();
        // HSE tidak mencatat drum lagi; pallet dipantau dari Limbah B3
        $this->actingAs(User::where('nik', '30000001')->first(), 'sanctum');
        $this->postJson('/api/pallets')->assertForbidden();
        $this->getJson('/api/b3/pallets')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', '40000001')->first(), 'sanctum');
        $this->getJson('/api/pallets')->assertForbidden();
    }
}
