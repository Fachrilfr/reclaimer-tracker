<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pemakaian solvent = pengambilan dari saldo hasil recovery, dicatat terpisah dari batch.
 * Saldo awal dari seeder: 1000 kg.
 */
class SolventUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 09:30:00');
        $this->as('OP-1001');
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'date' => '2026-10-20',
            'shift_id' => Shift::where('code', 'SHIFT-1')->value('id'),
            'weight' => 150,
            'purpose' => 'Pencucian mesin cetak',
            'notes' => null,
        ];
    }

    public function test_usage_reduces_balance_and_is_listed(): void
    {
        $this->postJson('/api/solvent-usages', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.weight', 150)
            ->assertJsonPath('data.balance_after', 850)
            ->assertJsonPath('data.used_by', 'Operator Aziz');

        $this->assertDatabaseHas('solvent_ledger', ['type' => 'out', 'amount' => 150, 'previous_balance' => 1000, 'current_balance' => 850, 'batch_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity_type' => 'solvent_usages']);

        // Saldo yang sama dipakai form batch
        $this->getJson('/api/batches/create')->assertJsonPath('data.solvent_balance', 850);

        $this->as('SPV-2001')->getJson('/api/solvent-usages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.purpose', 'Pencucian mesin cetak')
            ->assertJsonPath('meta.balance', 850);
    }

    public function test_usage_cannot_exceed_balance(): void
    {
        $this->postJson('/api/solvent-usages', $this->payload(['weight' => 1000.01]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.weight.0', 'Solvent yang diambil melebihi saldo (1.000,00 kg). Periksa kembali angkanya.');

        $this->postJson('/api/solvent-usages', $this->payload(['weight' => 1000]))->assertCreated()
            ->assertJsonPath('data.balance_after', 0);
    }

    public function test_fields_are_validated(): void
    {
        $this->postJson('/api/solvent-usages', $this->payload(['weight' => 0, 'purpose' => '', 'date' => '2026-10-21']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight', 'purpose', 'date']);
    }

    public function test_access_follows_matrix(): void
    {
        $this->as('SPV-2001')->postJson('/api/solvent-usages', $this->payload())->assertForbidden();
        $this->as('HSE-3001')->getJson('/api/solvent-usages')->assertForbidden();
        $this->as('MGT-4001')->getJson('/api/solvent-usages')->assertForbidden();
    }
}
