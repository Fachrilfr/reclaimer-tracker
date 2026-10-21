<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BatchHistoryTest extends TestCase
{
    use RefreshDatabase;

    private array $shifts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 16:00:00');
        $this->actingAs(User::where('nik', 'OP-1001')->first(), 'sanctum');
        $this->shifts = Shift::orderBy('id')->pluck('id')->all();

        $batch = fn (array $data) => $this->postJson('/api/batches', $data + [
            'shift_id' => $this->shifts[0], 'is_continuation' => false, 'process_status' => 'completed',
            'oil_temp' => 140, 'vapour_temp' => 50, 'notes' => null,
        ])->assertCreated()->json('data.id');

        // 10 Okt: memenuhi target
        $batch(['date' => '2026-10-10', 'ink_weight' => 500, 'recovery_weight' => 200]);
        // 15 Okt: di bawah target, suhu tidak normal, keterangan "Kuras residu"
        $batch(['date' => '2026-10-15', 'ink_weight' => 500, 'recovery_weight' => 100, 'oil_temp' => 170, 'notes' => 'Kuras residu']);
        // 20 Okt: rangkaian shift 1 dilanjutkan shift 2, masih belum selesai
        $root = $batch(['date' => '2026-10-20', 'ink_weight' => 500, 'recovery_weight' => 50, 'process_status' => 'in_progress']);
        $batch([
            'date' => '2026-10-20', 'shift_id' => $this->shifts[1], 'is_continuation' => true, 'parent_batch_id' => $root,
            'process_status' => 'in_progress', 'ink_weight' => 0, 'recovery_weight' => 60, 'notes' => 'Lanjutan Pkl 15.10',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function numbers($response): array
    {
        return array_column($response->json('data'), 'batch_number');
    }

    public function test_default_lists_newest_chain_first_with_meta(): void
    {
        $response = $this->getJson('/api/batches')->assertOk()
            ->assertJsonPath('meta.total_chains', 3)
            ->assertJsonPath('meta.total_batches', 4)
            ->assertJsonPath('meta.page', 1);

        $this->assertSame(['BR-20261020-001', 'BR-20261020-002', 'BR-20261015-001', 'BR-20261010-001'], $this->numbers($response));
        $response->assertJsonPath('data.1.chain_root_id', $response->json('data.0.id'));
    }

    public function test_filters_by_date_range_of_chain_start(): void
    {
        $this->assertSame(['BR-20261015-001', 'BR-20261010-001'],
            $this->numbers($this->getJson('/api/batches?from=2026-10-10&to=2026-10-15')));

        $this->getJson('/api/batches?from=2026-10-16&to=2026-10-15')->assertUnprocessable()->assertJsonValidationErrors(['to']);
    }

    public function test_filters_by_status(): void
    {
        $this->assertSame(['BR-20261015-001'], $this->numbers($this->getJson('/api/batches?status=below_target')));
        $this->assertSame(['BR-20261015-001'], $this->numbers($this->getJson('/api/batches?status=temp_abnormal')));
        // Rangkaian belum selesai tampil utuh: batch awal dan lanjutannya
        $this->assertSame(['BR-20261020-001', 'BR-20261020-002'], $this->numbers($this->getJson('/api/batches?status=in_progress')));
        $this->getJson('/api/batches?status=reviewed')->assertJsonPath('meta.total_chains', 0);
        $this->getJson('/api/batches?status=salah')->assertUnprocessable();
    }

    public function test_shift_and_search_match_any_batch_in_chain(): void
    {
        // Shift 2 hanya ada di batch lanjutan, tapi seluruh rangkaian ikut tampil
        $this->assertSame(['BR-20261020-001', 'BR-20261020-002'],
            $this->numbers($this->getJson('/api/batches?shift_id=' . $this->shifts[1])));

        $this->assertSame(['BR-20261015-001'], $this->numbers($this->getJson('/api/batches?q=kuras')));
        $this->assertSame(['BR-20261010-001'], $this->numbers($this->getJson('/api/batches?q=20261010')));
        $this->assertSame(['BR-20261020-001', 'BR-20261020-002'], $this->numbers($this->getJson('/api/batches?q=Pkl 15.10')));
        $this->getJson('/api/batches?q=Aziz')->assertJsonPath('meta.total_chains', 3);
        // Karakter wildcard SQL dicari sebagai teks biasa
        $this->getJson('/api/batches?q=%25')->assertJsonPath('meta.total_chains', 0);
    }

    public function test_pagination_is_per_chain(): void
    {
        $this->getJson('/api/batches?per_page=5&page=1')->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.to', 3);
        $this->getJson('/api/batches?per_page=5&page=2')->assertJsonCount(0, 'data');
    }
}
