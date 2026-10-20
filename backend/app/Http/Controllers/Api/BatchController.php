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
use App\Support\MaterialBalance;
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
        $machine = Machine::orderBy('id')->first();
        $today = now()->startOfDay();
        $maxDays = config('reclaimer.continuation_max_days');

        $candidates = ReclaimerBatch::with('shift')
            ->where('is_continuation', false)
            ->whereDate('date', '>=', $today->copy()->subDays($maxDays))
            ->orderByDesc('date')->orderByDesc('id')
            ->get()
            ->map(function (ReclaimerBatch $batch) {
                $chain = ReclaimerBatch::where('id', $batch->id)->orWhere('parent_batch_id', $batch->id);
                $input = (float) $chain->sum('total_input_weight');
                $recovery = (float) $chain->sum('recovery_weight');

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'date' => $batch->date->toDateString(),
                    'shift' => $batch->shift->name,
                    'chain_input' => $input,
                    'chain_recovery' => $recovery,
                    'chain_rate' => MaterialBalance::rate($recovery, $input),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => $today->toDateString(),
                'operator' => $request->user()->only(['id', 'nik', 'name']),
                'shifts' => Shift::where('is_active', true)->orderBy('id')->get(['id', 'name']),
                'suggested_shift_id' => Shift::current(now())?->id,
                'machine' => $machine?->only(['code', 'name', 'status', 'maintenance_reason']),
                'material' => Material::where('type', 'ink')->where('is_active', true)->value('name'),
                'solvent_balance' => SolventLedger::currentBalance(),
                'target_recovery_rate' => Parameter::number('target_recovery_rate'),
                'temperature' => config('reclaimer.temperature'),
                'continuation_candidates' => $candidates,
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
        return [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'date' => $batch->date->toDateString(),
            'shift' => $batch->shift?->name,
            'regu' => $batch->regu,
            'operator' => $batch->operator?->name,
            'is_continuation' => $batch->is_continuation,
            'parent_batch_number' => $batch->parent?->batch_number,
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
