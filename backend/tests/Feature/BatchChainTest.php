<?php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Proses yang belum selesai saat shift berganti dilanjutkan shift berikutnya.
 * Recovery rate dinilai per rangkaian, setelah rangkaian selesai.
 */
class BatchChainTest extends TestCase
{
    use RefreshDatabase;

    private int $shift1;

    private int $shift2;

    private int $shift3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 16:00:00');
        $this->actingAs(User::where('nik', 'OP-1001')->first(), 'sanctum');
        [$this->shift1, $this->shift2, $this->shift3] = Shift::orderBy('id')->pluck('id')->all();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function batch(array $data)
    {
        return $this->postJson('/api/batches', $data + [
            'date' => '2026-10-20',
            'shift_id' => $this->shift1,
            'is_continuation' => false,
            'parent_batch_id' => null,
            'process_status' => 'completed',
            'ink_weight' => 0,
            'recovery_weight' => 0,
            'oil_temp' => 140,
            'vapour_temp' => 50,
            'notes' => null,
        ]);
    }

    private function continuation(int $parentId, array $data)
    {
        return $this->batch($data + [
            'shift_id' => $this->shift2,
            'is_continuation' => true,
            'parent_batch_id' => $parentId,
            'notes' => 'Lanjutan Pkl 15.30',
        ]);
    }

    public function test_unfinished_batch_is_not_judged_against_target(): void
    {
        // Shift 1: 500 kg Tinta X masuk, baru 60 kg keluar (12%), proses belum selesai
        $this->batch(['ink_weight' => 500, 'recovery_weight' => 60, 'process_status' => 'in_progress'])
            ->assertCreated()
            ->assertJsonPath('data.process_status', 'in_progress')
            ->assertJsonPath('data.chain_complete', false)
            ->assertJsonPath('data.chain_recovery_rate', null)
            ->assertJsonPath('data.is_recovery_low', false)
            // Saldo solvent tetap bergerak per batch: 1000 + 60
            ->assertJsonPath('data.solvent_balance_after', 1060);

        $dashboard = $this->getJson('/api/dashboard?period=day&date=2026-10-20')->assertOk();
        $dashboard->assertJsonPath('data.totals.chain_count', 0)
            ->assertJsonPath('data.totals.input', 0)
            ->assertJsonPath('data.totals.open_chain_count', 1)
            ->assertJsonPath('data.totals.solvent_balance', 1060)
            ->assertJsonPath('data.tolerance.has_data', false)
            ->assertJsonPath('data.tolerance.is_below', false)
            ->assertJsonCount(0, 'data.action_items.low_recovery_chains')
            ->assertJsonPath('data.by_shift.0.chain_count', 0)
            ->assertJsonPath('data.breakdown.rows.0.is_complete', false)
            ->assertJsonPath('data.breakdown.rows.0.recovery_rate', null);

        $this->getJson('/api/batches/create')->assertOk()
            ->assertJsonCount(1, 'data.open_chains')
            ->assertJsonPath('data.open_chains.0.batch_number', 'BR-20261020-001')
            ->assertJsonPath('data.open_chains.0.input', 500)
            ->assertJsonPath('data.open_chains.0.recovery', 60);
    }

    public function test_chain_completes_with_combined_rate_and_counts_once(): void
    {
        $root = $this->batch(['ink_weight' => 600, 'recovery_weight' => 60, 'process_status' => 'in_progress'])
            ->json('data.id');

        // Shift 2 masih belum selesai, menambah recovery
        $middle = $this->continuation($root, ['recovery_weight' => 90, 'process_status' => 'in_progress'])
            ->assertCreated()
            ->assertJsonPath('data.chain_complete', false)
            ->json('data.id');

        // Shift 3 menyelesaikan rangkaian; boleh menautkan ke batch lanjutan sebelumnya
        $this->continuation($middle, ['shift_id' => $this->shift3, 'recovery_weight' => 60, 'notes' => 'Lanjutan 23:15, selesai'])
            ->assertCreated()
            ->assertJsonPath('data.parent_batch_number', 'BR-20261020-001')
            ->assertJsonPath('data.chain_complete', true)
            // (60 + 90 + 60) / 600 = 35%, sama dengan target, tidak di bawah
            ->assertJsonPath('data.chain_recovery_rate', 35)
            ->assertJsonPath('data.target_recovery_rate', 35)
            ->assertJsonPath('data.is_recovery_low', false);

        $this->assertSame(3, ReclaimerBatch::whereNotNull('target_recovery_rate')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'complete', 'entity_id' => $root]);

        $day = $this->getJson('/api/dashboard?period=day&date=2026-10-20')->assertOk();
        $day->assertJsonPath('data.totals.chain_count', 1)
            ->assertJsonPath('data.totals.batch_count', 3)
            ->assertJsonPath('data.totals.input', 600)
            ->assertJsonPath('data.totals.recovery', 210)
            ->assertJsonPath('data.totals.recovery_rate', 35)
            ->assertJsonPath('data.totals.open_chain_count', 0)
            // Seluruh rangkaian dihitung di shift batch awalnya, tidak dipecah per shift
            ->assertJsonPath('data.by_shift.0.recovery_rate', 35)
            ->assertJsonPath('data.by_shift.1.chain_count', 0)
            ->assertJsonPath('data.by_shift.2.chain_count', 0)
            ->assertJsonCount(1, 'data.breakdown.rows')
            ->assertJsonPath('data.breakdown.rows.0.batch_count', 3);

        $this->getJson('/api/dashboard?period=month&date=2026-10-20')
            ->assertJsonPath('data.totals.input', 600)
            ->assertJsonPath('data.totals.chain_count', 1);

        // Rangkaian yang sudah selesai tidak bisa dilanjutkan lagi
        $this->continuation($root, ['recovery_weight' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('errors.parent_batch_id.0', 'Rangkaian BR-20261020-001 sudah selesai. Catat sebagai batch baru.');
        $this->getJson('/api/batches/create')->assertJsonCount(0, 'data.open_chains');
    }

    public function test_chain_below_target_is_flagged_on_completion_only(): void
    {
        $root = $this->batch(['ink_weight' => 500, 'recovery_weight' => 50, 'process_status' => 'in_progress'])
            ->json('data.id');
        $this->getJson('/api/dashboard')->assertJsonCount(0, 'data.action_items.low_recovery_chains');

        // (50 + 50) / 500 = 20% < 35%
        $this->continuation($root, ['recovery_weight' => 50])
            ->assertCreated()
            ->assertJsonPath('data.chain_recovery_rate', 20)
            ->assertJsonPath('data.is_recovery_low', true);

        $this->assertSame(2, ReclaimerBatch::where('is_recovery_low', true)->count());

        $this->getJson('/api/dashboard?period=day&date=2026-10-20')->assertOk()
            ->assertJsonCount(1, 'data.action_items.low_recovery_chains')
            ->assertJsonPath('data.action_items.low_recovery_chains.0.batch_number', 'BR-20261020-001')
            ->assertJsonPath('data.action_items.low_recovery_chains.0.batch_count', 2)
            ->assertJsonPath('data.tolerance.is_below', true)
            ->assertJsonPath('data.tolerance.gap', -15)
            ->assertJsonPath('data.tolerance.below_target_count', 1);
    }

    public function test_target_snapshot_is_taken_when_chain_completes(): void
    {
        $root = $this->batch(['ink_weight' => 500, 'recovery_weight' => 100, 'process_status' => 'in_progress'])
            ->json('data.id');

        // Target diubah sebelum rangkaian selesai: yang dipakai target saat selesai
        Parameter::where('param_key', 'target_recovery_rate')->update(['param_value' => '45.0']);

        $this->continuation($root, ['recovery_weight' => 100])
            ->assertJsonPath('data.chain_recovery_rate', 40)
            ->assertJsonPath('data.target_recovery_rate', 45)
            ->assertJsonPath('data.is_recovery_low', true);

        // Target berubah lagi setelahnya: snapshot rangkaian tidak ikut berubah
        Parameter::where('param_key', 'target_recovery_rate')->update(['param_value' => '30.0']);
        $this->assertTrue(ReclaimerBatch::find($root)->is_recovery_low);
    }

    public function test_continuation_rules(): void
    {
        $root = $this->batch(['ink_weight' => 500, 'recovery_weight' => 60, 'process_status' => 'in_progress'])
            ->json('data.id');

        // Keterangan wajib mencatat jam
        $this->continuation($root, ['recovery_weight' => 10, 'notes' => 'Lanjutan'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.notes.0', 'Batch lanjutan wajib mencatat jam di keterangan, contoh: Lanjutan Pkl 15.30.');
        $this->continuation($root, ['recovery_weight' => 10, 'notes' => 'Lanjutan Pkl 25.10'])->assertUnprocessable();

        // Tidak boleh sebelum batch sebelumnya
        $this->continuation($root, ['recovery_weight' => 10, 'date' => '2026-10-19'])
            ->assertUnprocessable()->assertJsonValidationErrors(['date']);

        // Recovery gabungan tidak boleh melebihi Tinta X rangkaian (500)
        $this->continuation($root, ['recovery_weight' => 441])
            ->assertUnprocessable()->assertJsonValidationErrors(['recovery_weight']);

        // Status proses wajib dipilih
        $this->batch(['ink_weight' => 100, 'process_status' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['process_status']);

        $this->continuation($root, ['recovery_weight' => 440, 'notes' => 'Kuras residu Pkl 7.05'])->assertCreated();
    }
}
