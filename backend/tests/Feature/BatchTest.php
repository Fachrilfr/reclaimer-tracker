<?php

namespace Tests\Feature;

use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 09:30:00');
        $this->operator = User::where('nik', 'OP-1001')->first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-10-20',
            'shift_id' => Shift::where('code', 'SHIFT-1')->value('id'),
            'regu' => 'B2',
            'is_continuation' => false,
            'parent_batch_id' => null,
            'process_status' => 'completed',
            'ink_weight' => 300,
            'recovery_weight' => 120,
            'oil_temp' => 140,
            'vapour_temp' => 50,
            'notes' => 'Start Pkl 07.30',
        ], $overrides);
    }

    private function storeAs(User $user, array $overrides = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->postJson('/api/batches', $this->payload($overrides));
    }

    public function test_operator_saves_batch_and_numbers_are_right(): void
    {
        $response = $this->storeAs($this->operator);

        $response->assertCreated()
            ->assertJsonPath('data.batch_number', 'BR-20261020-001')
            ->assertJsonPath('data.total_input_weight', 300)
            ->assertJsonPath('data.residue_estimated_weight', 180)
            ->assertJsonPath('data.recovery_rate_percent', 40)
            ->assertJsonPath('data.residue_rate_percent', 60)
            ->assertJsonPath('data.solvent_balance_after', 1120)
            ->assertJsonPath('data.is_recovery_low', false)
            ->assertJsonPath('data.is_temp_abnormal', false)
            ->assertJsonPath('data.operator', 'Operator Aziz');

        // Buku besar: saldo awal 1000, hasil recovery masuk 120 -> 1120. Batch tidak mengurangi saldo.
        $this->assertDatabaseHas('solvent_ledger', ['type' => 'in', 'amount' => 120, 'previous_balance' => 1000, 'current_balance' => 1120]);
        $this->assertDatabaseMissing('solvent_ledger', ['type' => 'out']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity_type' => 'reclaimer_batches']);

        $this->storeAs($this->operator)->assertJsonPath('data.batch_number', 'BR-20261020-002');
    }

    public function test_batch_rejected_when_recovery_exceeds_input(): void
    {
        $this->storeAs($this->operator, ['recovery_weight' => 300.01])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recovery_weight']);

        $this->assertDatabaseCount('reclaimer_batches', 0);
        $this->assertDatabaseCount('solvent_ledger', 1);
    }

    public function test_batch_ignores_solvent_used_field(): void
    {
        // Solvent dipakai dicatat lewat /api/solvent-usages, bukan di batch
        $this->storeAs($this->operator, ['solvent_used_weight' => 200])
            ->assertCreated()
            ->assertJsonPath('data.total_input_weight', 300)
            ->assertJsonPath('data.solvent_balance_after', 1120);
    }

    public function test_temperatures_and_required_fields_are_validated(): void
    {
        $this->storeAs($this->operator, ['oil_temp' => null, 'vapour_temp' => null, 'ink_weight' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['oil_temp', 'vapour_temp']);

        $this->storeAs($this->operator, ['ink_weight' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ink_weight']);

        $this->storeAs($this->operator, ['date' => '2026-10-21'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_abnormal_temperature_is_saved_and_flagged(): void
    {
        $this->storeAs($this->operator, ['oil_temp' => 165, 'vapour_temp' => 39])
            ->assertCreated()
            ->assertJsonPath('data.is_temp_abnormal', true);
    }

    public function test_low_recovery_is_flagged_against_target(): void
    {
        // 90 / 300 = 30% < target 35%
        $this->storeAs($this->operator, ['recovery_weight' => 90])
            ->assertCreated()
            ->assertJsonPath('data.recovery_rate_percent', 30)
            ->assertJsonPath('data.is_recovery_low', true);
    }

    public function test_only_roles_with_isi_can_save(): void
    {
        $this->storeAs(User::where('nik', 'SPV-2001')->first())->assertForbidden();
        $this->storeAs(User::where('nik', 'HSE-3001')->first())->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'SPV-2001')->first(), 'sanctum')->getJson('/api/batches')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::where('nik', 'MGT-4001')->first(), 'sanctum')->getJson('/api/batches')->assertForbidden();
    }

    public function test_form_data_contains_balance_shift_and_candidates(): void
    {
        $this->storeAs($this->operator);
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->operator, 'sanctum')->getJson('/api/batches/create')
            ->assertOk()
            ->assertJsonPath('data.solvent_balance', 1120)
            ->assertJsonPath('data.target_recovery_rate', 35)
            ->assertJsonPath('data.suggested_shift_id', Shift::where('code', 'SHIFT-1')->value('id'))
            ->assertJsonPath('data.machine.status', 'operating')
            ->assertJsonCount(0, 'data.open_chains');
    }
}
