<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Saldo awal solvent di Data master (HSE dan Super Admin). Seeder mengisi 1000 kg. */
class SolventOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as(string $nik): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::where('nik', $nik)->first(), 'sanctum');
    }

    /** Operator: input dengan hasil solvent 100 kg, lalu ambil solvent $usage kg. */
    private function mutations(float $usage): void
    {
        $shift = Shift::orderBy('id')->value('id');
        $this->as('10000001')->postJson('/api/batches', [
            'date' => '2026-10-20', 'regu' => 'A', 'shift_id' => $shift, 'is_continuation' => false, 'parent_batch_id' => null,
            'process_status' => 'completed', 'ink_weight' => 500, 'recovery_weight' => 100, 'oil_temp' => 140, 'vapour_temp' => 50,
        ])->assertCreated();
        $this->postJson('/api/solvent-usages', ['date' => '2026-10-20', 'regu' => 'A', 'shift_id' => $shift, 'weight' => $usage, 'purpose' => 'Cuci mesin'])
            ->assertCreated();
    }

    public function test_opening_balance_is_set_before_the_app_is_used(): void
    {
        $this->as('30000001')->getJson('/api/master')->assertOk()
            ->assertJsonPath('data.solvent_opening.amount', 1000)
            ->assertJsonPath('data.solvent_opening.mutation_count', 0);

        $this->putJson('/api/master/solvent-opening', ['amount' => 750.5, 'date' => '2026-10-01'])->assertOk()
            ->assertJsonPath('message', 'Saldo awal solvent 750,50 kg per 1 Okt 2026 tersimpan.')
            ->assertJsonPath('data.solvent_opening.amount', 750.5)
            ->assertJsonPath('data.solvent_opening.date', '2026-10-01')
            ->assertJsonPath('data.solvent_opening.current_balance', 750.5);

        $this->assertSame(1, SolventLedger::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'set_opening_balance']);
    }

    public function test_changing_it_later_shifts_every_balance_after_it(): void
    {
        $this->as('30000001')->putJson('/api/master/solvent-opening', ['amount' => 750, 'date' => '2026-10-01'])->assertOk();
        $this->mutations(50);
        $this->assertEquals(800, SolventLedger::currentBalance());

        $this->as('50000001')->putJson('/api/master/solvent-opening', ['amount' => 900, 'date' => '2026-10-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->putJson('/api/master/solvent-opening', ['amount' => 900, 'date' => '2026-10-01', 'reason' => 'Hitung fisik ulang'])->assertOk()
            ->assertJsonPath('message', 'Saldo awal solvent 900,00 kg per 1 Okt 2026 tersimpan. Semua saldo sesudahnya bergeser +150,00 kg, saldo sekarang 950,00 kg.');

        $this->assertEquals(950, SolventLedger::currentBalance());
        $this->assertEquals(1000, ReclaimerBatch::first()->solvent_balance_after);
        $this->assertEquals(950, SolventUsage::first()->balance_after);
        $this->assertEquals([900, 1000, 950], SolventLedger::orderBy('id')->pluck('current_balance')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('Hitung fisik ulang', AuditLog::where('action', 'set_opening_balance')->latest('id')->first()->new_values['reason']);
    }

    public function test_balance_may_not_turn_negative_and_date_stays_first(): void
    {
        $this->mutations(1050); // 1000 + 100 - 1050 = 50

        $this->as('30000001')->putJson('/api/master/solvent-opening', ['amount' => 900, 'date' => '2026-10-01', 'reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Saldo awal terlalu kecil: saldo solvent akan menjadi minus. Saldo awal paling sedikit 950,00 kg.');

        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->putJson('/api/master/solvent-opening', ['amount' => 1000, 'date' => '2026-10-22', 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_only_roles_that_manage_master_data(): void
    {
        foreach (['10000001', '20000001', '40000001'] as $nik) {
            $this->as($nik)->putJson('/api/master/solvent-opening', ['amount' => 1, 'date' => '2026-10-01'])->assertForbidden();
        }
        $this->as('50000001')->putJson('/api/master/solvent-opening', ['amount' => 1, 'date' => '2026-10-01'])->assertOk();
    }
}
