<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Support\BatchChain;
use App\Support\BatchHistoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Data pendukung form batch: shift, mesin, saldo solvent, target, rentang suhu,
     * dan batch awal yang bisa dilanjutkan.
     */
    public function create(Request $request): JsonResponse
    {
        $machine = Machine::main()?->load('reporter');
        $today = now()->startOfDay();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => $today->toDateString(),
                'operator' => $request->user()->only(['id', 'nik', 'name']),
                'shifts' => Shift::where('is_active', true)->orderBy('id')->get(['id', 'name']),
                'suggested_shift_id' => Shift::current(now())?->id,
                'machine' => $machine?->summary(),
                'material' => Material::where('type', 'ink')->where('is_active', true)->value('name'),
                'solvent_balance' => SolventLedger::currentBalance(),
                'target_recovery_rate' => Parameter::number('target_recovery_rate'),
                'temperature' => config('reclaimer.temperature'),
                // Rangkaian yang menunggu batch lanjutan dari shift berikutnya
                'open_chains' => BatchChain::open()->map(fn (BatchChain $chain) => $chain->toArray())->values(),
            ],
        ]);
    }

    /** Batch terbaru, untuk dicek ulang operator dan dibaca role lain. */
    /**
     * Riwayat batch dengan filter tanggal, cari, status, dan shift, dihalaman per
     * rangkaian. Setiap baris tetap satu batch; batch satu rangkaian tampil berurutan.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:' . implode(',', BatchHistoryQuery::STATUSES)],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ], [
            'from.date_format' => 'Tanggal awal tidak valid.',
            'to.date_format' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'status.in' => 'Status filter tidak dikenal.',
            'shift_id.exists' => 'Shift tidak ditemukan.',
            'q.max' => 'Kata kunci paling panjang 100 karakter.',
        ]);

        $roots = BatchHistoryQuery::roots($filters);
        $totalBatches = ReclaimerBatch::whereIn('id', (clone $roots)->select('id'))
            ->orWhereIn('parent_batch_id', (clone $roots)->select('id'))
            ->count();

        $page = $roots->with(['shift', 'operator', 'reviewer', 'continuations' => fn ($q) => $q->with(['shift', 'operator', 'parent'])])
            ->paginate($filters['per_page'] ?? 20);

        $rows = $page->getCollection()->flatMap(function (ReclaimerBatch $root) {
            $chainSize = $root->continuations->count() + 1;
            $batches = collect([$root])->concat($root->continuations->each->setRelation('parent', $root));

            return $batches->map(fn (ReclaimerBatch $batch) => self::present($batch, $chainSize) + ['chain_root_id' => $root->id]);
        });

        return response()->json([
            'status' => 'success',
            'data' => $rows->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total_chains' => $page->total(),
                'total_batches' => $totalBatches,
                // Pilihan filter shift, juga untuk role yang tidak memuat data form batch
                'shifts' => Shift::where('is_active', true)->orderBy('id')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(StoreBatchRequest $request, RecordBatch $recordBatch): JsonResponse
    {
        $batch = $recordBatch->handle($request, $request->batchData());
        $batch->load(['shift', 'operator', 'parent']);

        return response()->json([
            'status' => 'success',
            'message' => "Batch {$batch->batch_number} tersimpan.",
            'data' => self::present($batch),
        ], 201);
    }

    public static function present(ReclaimerBatch $batch, ?int $chainSize = null): array
    {
        $root = $batch->root();
        $chainComplete = $root->chain_completed_at !== null;

        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'date' => $batch->date->toDateString(),
            'shift' => $batch->shift?->name,
            'regu' => $batch->regu,
            'operator' => $batch->operator?->name,
            'is_continuation' => $batch->is_continuation,
            'parent_batch_number' => $batch->parent?->batch_number,
            'process_status' => $batch->process_status,
            // Recovery rate rangkaian baru ada setelah rangkaian selesai; sebelumnya
            // tampil sebagai "menunggu lanjutan"
            'chain_complete' => $chainComplete,
            'chain_recovery_rate' => $chainComplete ? $root->chain_recovery_rate : null,
            'chain_batch_count' => $chainSize ?? $root->continuations()->count() + 1,
            'target_recovery_rate' => $batch->target_recovery_rate,
            'review' => $root->reviewSummary(),
            'ink_weight' => $batch->ink_weight,
            'solvent_used_weight' => $batch->solvent_used_weight,
            'recovery_weight' => $batch->recovery_weight,
            'total_input_weight' => $batch->total_input_weight,
            'residue_estimated_weight' => $batch->residue_estimated_weight,
            'recovery_rate_percent' => $batch->recovery_rate_percent,
            'residue_rate_percent' => $batch->residue_rate_percent,
            'solvent_balance_after' => $batch->solvent_balance_after,
            'oil_temp' => $batch->oil_temp,
            'vapour_temp' => $batch->vapour_temp,
            'is_temp_abnormal' => $batch->is_temp_abnormal,
            'is_recovery_low' => $batch->is_recovery_low,
            'notes' => $batch->notes,
        ];
    }
}
