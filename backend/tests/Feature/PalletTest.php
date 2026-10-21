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
        $this->operator = User::where('nik', 'OP-1001')->first();
        $this->actingAs($this->operator, 'sanctum');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operator_fills_pallet_with_three_drums(): void
    {
        $pallet = $this->postJson('/api/pallets', ['start_date' => '2026-10-01'])
            ->assertCreated()
            ->assertJsonPath('data.pallet_number', 'PLT-202610-001')
            ->assertJsonPath('data.expiry_date', '2026-12-30')
            // 1 Okt + 90 hari = 30 Des; dari 20 Okt sisa 71 hari
            ->assertJsonPath('data.storage.days_remaining', 71)
            ->assertJsonPath('data.storage.level', 'aman')
            ->json('data');

        $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => '2026-10-05'])
            ->assertCreated()->assertJsonPath('data.drum_count', 1)->assertJsonPath('data.status', 'filling');
        $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => '2026-10-12'])
            ->assertCreated()->assertJsonPath('data.drum_count', 2);
        $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => '2026-10-19'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'full')
            ->assertJsonPath('data.full_date', '2026-10-19')
            ->assertJsonPath('message', 'Drum ke-3 tercatat. Pallet PLT-202610-001 penuh dan dikirim ke TPS.');

        $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => '2026-10-20'])
            ->assertUnprocessable()->assertJsonValidationErrors(['filled_date']);

        $this->assertDatabaseCount('b3_drum_logs', 3);
        $this->getJson('/api/pallets')->assertOk()->assertJsonPath('data.filling', null);
    }

    public function test_full_pallet_is_sent_to_tps(): void
    {
        $pallet = $this->postJson('/api/pallets', ['start_date' => '2026-10-15'])
            ->assertCreated()
            // Batas kirim ke TPS 7 hari sejak mulai terisi: 22 Okt, sisa 2 hari per 20 Okt
            ->assertJsonPath('data.tps_due_date', '2026-10-22')
            ->assertJsonPath('data.tps.level', 'perhatian')
            ->assertJsonPath('data.tps.days_remaining', 2)
            ->assertJsonPath('data.tps.sent_at', null)
            ->json('data');

        foreach (['2026-10-16', '2026-10-18'] as $date) {
            $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => $date])
                ->assertJsonPath('data.tps.sent_at', null);
        }

        $this->postJson("/api/pallets/{$pallet['id']}/drums", ['filled_date' => '2026-10-20'])
            ->assertCreated()
            ->assertJsonPath('message', 'Drum ke-3 tercatat. Pallet PLT-202610-001 penuh dan dikirim ke TPS.')
            ->assertJsonPath('data.tps.level', 'terkirim')
            ->assertJsonPath('data.tps.sent_at', '2026-10-20')
            ->assertJsonPath('data.tps.late_days', 0)
            ->assertJsonPath('data.tps.needs_action', false)
            // Masa simpan TPS ke vendor (HSE) tetap 90 hari dari mulai terisi
            ->assertJsonPath('data.expiry_date', '2027-01-13');
    }

    public function test_tps_level_follows_delivery_deadline(): void
    {
        $cases = [
            '2026-10-19' => [6, 'aman'],
            '2026-10-16' => [3, 'perhatian'],
            '2026-10-14' => [1, 'mendesak'],
            '2026-10-13' => [0, 'mendesak'],  // batas hari ini
            '2026-10-10' => [-3, 'kritis'],   // lewat 3 hari
        ];

        foreach ($cases as $start => [$days, $level]) {
            $pallet = B3Pallet::create([
                'pallet_number' => "PLT-TPS-{$start}",
                'start_date' => $start,
                'max_storage_days' => 90,
                'expiry_date' => Carbon::parse($start)->addDays(90),
                'tps_due_date' => Carbon::parse($start)->addDays(7),
                'status' => 'filling',
            ]);

            $status = $pallet->tpsStatus();
            $this->assertSame($days, $status->daysRemaining, "start {$start}");
            $this->assertSame($level, $status->level, "start {$start}");
        }

        // Dikirim terlambat tetap tercatat berapa hari lewatnya
        $late = B3Pallet::create([
            'pallet_number' => 'PLT-TPS-LATE', 'start_date' => '2026-10-01', 'max_storage_days' => 90,
            'expiry_date' => '2026-12-30', 'tps_due_date' => '2026-10-08', 'sent_to_tps_at' => '2026-10-11', 'status' => 'full',
        ]);
        $this->assertSame('terkirim', $late->tpsStatus()->level);
        $this->assertSame(3, $late->tpsStatus()->lateDays);
    }

    public function test_dashboard_lists_pallets_late_for_tps(): void
    {
        $this->postJson('/api/pallets', ['start_date' => '2026-10-10'])->assertCreated();

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonCount(1, 'data.tps_pallets')
            ->assertJsonCount(1, 'data.action_items.tps_pallets')
            ->assertJsonPath('data.action_items.tps_pallets.0.tps.level', 'kritis')
            ->assertJsonPath('data.action_items.tps_pallets.0.tps.days_remaining', -3);
    }

    public function test_pallet_and_drum_notes_appear_in_history(): void
    {
        $id = $this->postJson('/api/pallets', ['start_date' => '2026-10-15', 'notes' => 'Pallet kayu baru'])
            ->assertJsonPath('data.notes', 'Pallet kayu baru')
            ->json('data.id');
        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-16']);
        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-18', 'notes' => 'Tutup drum diganti']);

        $this->getJson('/api/pallets')->assertOk()
            ->assertJsonPath('data.recent.0.notes', 'Pallet kayu baru')
            ->assertJsonCount(1, 'data.recent.0.drum_notes')
            ->assertJsonPath('data.recent.0.drum_notes.0.sequence', 2)
            ->assertJsonPath('data.recent.0.drum_notes.0.notes', 'Tutup drum diganti')
            ->assertJsonPath('data.filling.notes', 'Pallet kayu baru');
    }

    public function test_only_one_pallet_can_be_filling(): void
    {
        $this->postJson('/api/pallets', ['start_date' => '2026-10-10'])->assertCreated();

        $this->postJson('/api/pallets', ['start_date' => '2026-10-11'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'Pallet PLT-202610-001 masih diisi. Catat drum sampai pallet penuh sebelum memulai pallet baru.');
    }

    public function test_drum_date_cannot_be_before_pallet_start_or_previous_drum(): void
    {
        $id = $this->postJson('/api/pallets', ['start_date' => '2026-10-10'])->json('data.id');

        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-09'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.filled_date.0', 'Tanggal drum penuh tidak boleh sebelum 10 Okt 2026.');

        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-15'])->assertCreated();
        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-14'])->assertUnprocessable();
        $this->postJson("/api/pallets/{$id}/drums", ['filled_date' => '2026-10-21'])->assertUnprocessable();
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

        foreach ($cases as $start => [$days, $level]) {
            $pallet = B3Pallet::create([
                'pallet_number' => "PLT-UJI-{$start}",
                'start_date' => $start,
                'max_storage_days' => 90,
                'expiry_date' => Carbon::parse($start)->addDays(90),
                'status' => 'full',
            ]);

            $status = $pallet->storageStatus();
            $this->assertSame($days, $status->daysRemaining, "start {$start}");
            $this->assertSame($level, $status->level, "start {$start}");
        }
    }

    public function test_access_follows_matrix(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'SPV-2001')->first(), 'sanctum');
        $this->getJson('/api/pallets')->assertOk();
        $this->postJson('/api/pallets', ['start_date' => '2026-10-10'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        // HSE tidak mencatat drum lagi; pallet dipantau dari Limbah B3
        $this->actingAs(User::where('nik', 'HSE-3001')->first(), 'sanctum');
        $this->postJson('/api/pallets', ['start_date' => '2026-10-10'])->assertForbidden();
        $this->getJson('/api/b3/pallets')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'MGT-4001')->first(), 'sanctum');
        $this->getJson('/api/pallets')->assertForbidden();
    }
}
