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
    public function index(): JsonResponse
    {
        $batches = ReclaimerBatch::with(['shift', 'operator', 'parent'])
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ReclaimerBatch $batch) => self::present($batch));

        return response()->json(['status' => 'success', 'data' => $batches]);
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

    public static function present(ReclaimerBatch $batch): array
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
            'chain_batch_count' => $root->continuations()->count() + 1,
            'target_recovery_rate' => $batch->target_recovery_rate,
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
