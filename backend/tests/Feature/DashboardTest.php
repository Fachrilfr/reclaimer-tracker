<?php

namespace Tests\Feature;

use App\Models\B3Pallet;
use App\Models\Shift;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 14:00:00');
        $this->actingAs(User::where('nik', 'OP-1001')->first(), 'sanctum');

        $shift1 = Shift::where('code', 'SHIFT-1')->value('id');
        $shift2 = Shift::where('code', 'SHIFT-2')->value('id');
        $batch = fn (array $data) => $this->postJson('/api/batches', $data + [
            'shift_id' => $shift1, 'is_continuation' => false, 'process_status' => 'completed', 'oil_temp' => 140, 'vapour_temp' => 50,
        ])->assertCreated();

        // 20 Okt: 10/100 (10%) di shift 1 dan 360/900 (40%) di shift 2
        $batch(['date' => '2026-10-20', 'ink_weight' => 100, 'recovery_weight' => 10]);
        $batch(['date' => '2026-10-20', 'ink_weight' => 900, 'recovery_weight' => 360, 'shift_id' => $shift2]);
        // 3 Okt: 200/500 (40%), di bulan yang sama tapi minggu lain
        $batch(['date' => '2026-10-03', 'ink_weight' => 500, 'recovery_weight' => 200]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_totals_use_sums_and_show_per_shift(): void
    {
        $response = $this->getJson('/api/dashboard?period=day&date=2026-10-20')->assertOk();

        // (10 + 360) / (100 + 900) = 37%, bukan rata-rata (10% + 40%) / 2 = 25%
        $response->assertJsonPath('data.totals.batch_count', 2)
            ->assertJsonPath('data.totals.input', 1000)
            ->assertJsonPath('data.totals.recovery', 370)
            ->assertJsonPath('data.totals.residue', 630)
            ->assertJsonPath('data.totals.recovery_rate', 37)
            ->assertJsonPath('data.totals.solvent_balance', 1570)
            ->assertJsonPath('data.by_shift.0.recovery_rate', 10)
            ->assertJsonPath('data.by_shift.1.recovery_rate', 40)
            ->assertJsonPath('data.by_shift.2.batch_count', 0)
            ->assertJsonPath('data.breakdown.unit', 'chain')
            ->assertJsonCount(2, 'data.breakdown.rows');
    }

    public function test_monthly_breakdown_is_per_week_and_yearly_per_month(): void
    {
        $month = $this->getJson('/api/dashboard?period=month&date=2026-10-20')->assertOk();
        $month->assertJsonPath('data.totals.batch_count', 3)
            ->assertJsonPath('data.totals.recovery_rate', 38)
            ->assertJsonPath('data.breakdown.unit', 'week')
            // 1-4 Okt (Kamis s/d Minggu), lalu minggu berikutnya sampai minggu berjalan
            ->assertJsonPath('data.breakdown.rows.0.start', '2026-10-01')
            ->assertJsonPath('data.breakdown.rows.0.end', '2026-10-04')
            ->assertJsonPath('data.breakdown.rows.0.batch_count', 1)
            ->assertJsonPath('data.breakdown.rows.3.start', '2026-10-19')
            ->assertJsonPath('data.breakdown.rows.3.batch_count', 2)
            ->assertJsonCount(4, 'data.breakdown.rows');

        $this->getJson('/api/dashboard?period=year&date=2026-10-20')->assertOk()
            ->assertJsonPath('data.breakdown.unit', 'month')
            ->assertJsonCount(10, 'data.breakdown.rows')
            ->assertJsonPath('data.breakdown.rows.9.batch_count', 3);
    }

    public function test_weekly_period_runs_from_the_chosen_start_with_daily_breakdown(): void
    {
        // Tanpa tanggal akhir: 7 hari termasuk tanggal awal
        $this->getJson('/api/dashboard?period=week&date=2026-10-14')->assertOk()
            ->assertJsonPath('data.start', '2026-10-14')
            ->assertJsonPath('data.end', '2026-10-20')
            ->assertJsonPath('data.totals.batch_count', 2)
            ->assertJsonPath('data.breakdown.unit', 'day')
            ->assertJsonCount(7, 'data.breakdown.rows')
            ->assertJsonPath('data.trend.unit', 'day')
            ->assertJsonCount(7, 'data.trend.points');

        $this->getJson('/api/dashboard?period=week&date=2026-10-01&end=2026-10-05')->assertOk()
            ->assertJsonPath('data.end', '2026-10-05')
            ->assertJsonPath('data.totals.batch_count', 1)
            ->assertJsonCount(5, 'data.breakdown.rows');
    }

    public function test_hse_dashboard_shows_residue_estimate_vendor_weight_and_tps_pallets(): void
    {
        $hse = User::where('nik', 'HSE-3001')->first();
        SuratJalan::create([
            'surat_jalan_number' => 'SJ-UJI-001', 'vendor_id' => Vendor::orderBy('id')->value('id'), 'handover_date' => '2026-10-20',
            'total_pallets' => 0, 'weight_kg' => 450.5, 'vendor_officer_name' => 'Petugas', 'created_by' => $hse->id,
        ]);
        B3Pallet::create([
            'pallet_number' => 'PLT-202610-002', 'start_date' => '2026-10-10', 'max_storage_days' => 90, 'drum_count' => 4,
            'sent_to_tps_at' => '2026-10-12', 'expiry_date' => '2027-01-10', 'status' => 'full',
        ]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($hse, 'sanctum');

        $this->getJson('/api/dashboard/hse?period=month&date=2026-10-20')->assertOk()
            // Residu estimasi = Tinta X - hasil solvent: (1000 - 370) + (500 - 200)
            ->assertJsonPath('data.totals.residue', 930)
            ->assertJsonPath('data.totals.chain_count', 3)
            ->assertJsonPath('data.totals.vendor_weight', 450.5)
            ->assertJsonPath('data.totals.surat_jalan_count', 1)
            ->assertJsonPath('data.totals.tps_pallet_count', 1)
            ->assertJsonCount(3, 'data.inputs')
            // Terbaru dulu
            ->assertJsonPath('data.inputs.2.date', '2026-10-03')
            ->assertJsonPath('data.inputs.2.residue', 300)
            ->assertJsonPath('data.breakdown.unit', 'week')
            ->assertJsonPath('data.breakdown.rows.3.vendor_weight', 450.5);

        // Laporan residu memakai angka berat residu yang sama
        $this->getJson('/api/reports/b3?period=month&date=2026-10-20')->assertOk()
            ->assertJsonPath('data.summary.residue', 930)
            ->assertJsonPath('data.summary.residue_chain_count', 3);

        $this->getJson('/api/dashboard/hse?period=day&date=2026-10-20')->assertOk()
            ->assertJsonPath('data.totals.residue', 630)
            ->assertJsonPath('data.breakdown', null);

        // Khusus akun HSE
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'SPV-2001')->first(), 'sanctum');
        $this->getJson('/api/dashboard/hse')->assertForbidden();
    }

    public function test_notifications_carry_the_pallet_being_filled_for_the_banner(): void
    {
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.filling_pallet', null);

        B3Pallet::create([
            'pallet_number' => 'PLT-202610-003', 'start_date' => '2026-10-19', 'max_storage_days' => 90, 'drum_count' => 2,
            'status' => 'filling',
        ]);

        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('data.filling_pallet.pallet_number', 'PLT-202610-003')
            ->assertJsonPath('data.filling_pallet.drum_count', 2)
            ->assertJsonPath('data.filling_pallet.start_date', '2026-10-19');
    }

    public function test_action_items_list_low_recovery_and_urgent_pallets(): void
    {
        B3Pallet::create([
            'pallet_number' => 'PLT-202608-001', 'start_date' => '2026-07-26', 'max_storage_days' => 90,
            'sent_to_tps_at' => '2026-08-01', 'expiry_date' => '2026-10-30', 'status' => 'full',
        ]);
        // Masih diisi di area reclaimer: belum masuk daftar masa simpan di TPS
        B3Pallet::create([
            'pallet_number' => 'PLT-202610-001', 'start_date' => '2026-10-15', 'max_storage_days' => 90,
            'status' => 'filling',
        ]);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(1, 'data.action_items.low_recovery_chains')
            ->assertJsonPath('data.action_items.low_recovery_chains.0.recovery_rate', 10)
            ->assertJsonCount(1, 'data.action_items.pallets')
            ->assertJsonPath('data.action_items.pallets.0.pallet_number', 'PLT-202608-001')
            ->assertJsonPath('data.action_items.pallets.0.storage.days_remaining', 10)
            ->assertJsonPath('data.action_items.pallets.0.storage.level', 'mendesak')
            ->assertJsonCount(1, 'data.active_pallets')
            ->assertJsonCount(1, 'data.tps_pallets');
    }

    public function test_dashboard_access_follows_matrix(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'ADM-5001')->first(), 'sanctum');
        $this->getJson('/api/dashboard')->assertForbidden();

        $this->getJson('/api/dashboard?date=2026-10-21')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'MGT-4001')->first(), 'sanctum');
        $this->getJson('/api/dashboard?date=2026-10-21')->assertUnprocessable();
    }
}
