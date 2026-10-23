<?php

namespace Tests\Feature;

use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChainReviewTest extends TestCase
{
    use RefreshDatabase;

    private int $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->as('OP-1001');

        // Rangkaian dua batch, gabungan (50 + 50) / 500 = 20%, di bawah toleransi 40%
        $base = [
            'date' => '2026-10-20', 'shift_id' => Shift::orderBy('id')->value('id'), 'oil_temp' => 140,
            'vapour_temp' => 50,
        ];
        $this->root = $this->postJson('/api/batches', $base + [
            'is_continuation' => false, 'process_status' => 'in_progress', 'ink_weight' => 500,
            'recovery_weight' => 50,
        ])->json('data.id');
        $this->postJson('/api/batches', $base + [
            'is_continuation' => true, 'parent_batch_id' => $this->root, 'process_status' => 'completed',
            'ink_weight' => 0, 'recovery_weight' => 50, 'notes' => 'Lanjutan Pkl 15.30',
        ])->assertCreated();
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

    public function test_supervisor_review_removes_chain_from_action_items(): void
    {
        $this->as('SPV-2001')->getJson('/api/dashboard')->assertJsonCount(1, 'data.action_items.low_recovery_chains')
            ->assertJsonPath('data.action_items.low_recovery_chains.0.review', null);

        $this->postJson("/api/batch-chains/{$this->root}/review", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['notes']);

        $this->postJson("/api/batch-chains/{$this->root}/review", ['notes' => 'Suhu uap terlalu rendah, operator sudah diarahkan'])
            ->assertOk()
            ->assertJsonPath('message', 'Rangkaian BR-20261020-001 sudah ditinjau.')
            ->assertJsonPath('data.by', 'Supervisor Reclaimer');

        $this->getJson('/api/dashboard')->assertJsonCount(0, 'data.action_items.low_recovery_chains')
            // Tetap tercatat di bawah target di rincian, dengan catatan tinjauannya
            ->assertJsonPath('data.breakdown.rows.0.is_below_target', true)
            ->assertJsonPath('data.breakdown.rows.0.review.notes', 'Suhu uap terlalu rendah, operator sudah diarahkan');

        // Daftar batch menampilkan tinjauan untuk kedua batch rangkaian
        $this->as('OP-1001')->getJson('/api/batches')
            ->assertJsonPath('data.0.review.by', 'Supervisor Reclaimer')
            ->assertJsonPath('data.1.review.by', 'Supervisor Reclaimer');

        $this->assertDatabaseHas('audit_logs', ['action' => 'review', 'entity_id' => $this->root]);

        // Tidak bisa ditinjau dua kali
        $this->as('SPV-2001')->postJson("/api/batch-chains/{$this->root}/review", ['notes' => 'lagi'])
            ->assertUnprocessable()->assertJsonPath('errors.notes.0', 'Rangkaian BR-20261020-001 sudah ditinjau.');
    }

    public function test_review_via_continuation_id_targets_the_chain(): void
    {
        $continuation = ReclaimerBatch::where('parent_batch_id', $this->root)->value('id');

        $this->as('SPV-2001')->postJson("/api/batch-chains/{$continuation}/review", ['notes' => 'Ditangani'])->assertOk();
        $this->assertTrue(ReclaimerBatch::find($this->root)->supervisor_reviewed);
    }

    public function test_only_supervisor_can_review(): void
    {
        foreach (['OP-1001', 'HSE-3001', 'MGT-4001', 'ADM-5001'] as $nik) {
            $this->as($nik)->postJson("/api/batch-chains/{$this->root}/review", ['notes' => 'x'])->assertForbidden();
        }
    }

    public function test_chain_meeting_target_or_unfinished_cannot_be_reviewed(): void
    {
        $base = [
            'date' => '2026-10-20', 'shift_id' => Shift::orderBy('id')->value('id'), 'is_continuation' => false,
            'oil_temp' => 140, 'vapour_temp' => 50, 'ink_weight' => 500,
        ];
        $good = $this->postJson('/api/batches', $base + ['process_status' => 'completed', 'recovery_weight' => 200])->json('data.id');
        $open = $this->postJson('/api/batches', $base + ['process_status' => 'in_progress', 'recovery_weight' => 20])->json('data.id');

        $this->as('SPV-2001');
        $this->postJson("/api/batch-chains/{$good}/review", ['notes' => 'x'])->assertUnprocessable()
            ->assertJsonPath('errors.notes.0', 'Rangkaian ini memenuhi toleransi solvent, tidak perlu ditinjau.');
        $this->postJson("/api/batch-chains/{$open}/review", ['notes' => 'x'])->assertUnprocessable()
            ->assertJsonPath('errors.notes.0', 'Rangkaian ini belum selesai, jadi belum dinilai terhadap toleransi.');
    }

    public function test_unreviewed_chain_disappears_after_14_days(): void
    {
        $this->as('SPV-2001');
        Carbon::setTestNow('2026-11-02 10:00:00'); // hari ke-14 sejak selesai
        $this->getJson('/api/dashboard')->assertJsonCount(1, 'data.action_items.low_recovery_chains');

        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->getJson('/api/dashboard')->assertJsonCount(0, 'data.action_items.low_recovery_chains');
    }
}
