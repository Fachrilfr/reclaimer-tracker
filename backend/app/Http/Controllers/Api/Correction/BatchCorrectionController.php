<?php

namespace App\Http\Controllers\Api\Correction;

use App\Actions\Corrections\CorrectBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Support\Correction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Koreksi data: input reclaimer. */
class BatchCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request);

        $page = ReclaimerBatch::with(['shift', 'operator', 'parent', 'corrector'])
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('date', '<=', $to))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters) {
                $like = Correction::like($filters['q']);
                $q->where(fn (Builder $w) => $w->where('batch_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('regu', 'like', $like)
                    ->orWhereHas('operator', fn (Builder $u) => $u->where('name', 'like', $like)));
            })
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(20);

        // Input terakhir tiap proses: hanya input itu yang bisa dihapus atau diubah statusnya
        $rootIds = $page->getCollection()->map(fn (ReclaimerBatch $b) => $b->parent_batch_id ?? $b->id)->unique()->values();
        $lastIds = ReclaimerBatch::whereIn('parent_batch_id', $rootIds)->groupBy('parent_batch_id')
            ->selectRaw('parent_batch_id, max(id) as last_id')->pluck('last_id', 'parent_batch_id');
        $lastNumbers = ReclaimerBatch::whereIn('id', $lastIds->values())->pluck('batch_number', 'id');

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(function (ReclaimerBatch $b) use ($lastIds, $lastNumbers) {
                $rootId = $b->parent_batch_id ?? $b->id;
                $lastId = (int) ($lastIds[$rootId] ?? $rootId);

                return [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'date' => $b->date->toDateString(),
                    'shift_id' => $b->shift_id,
                    'shift' => $b->shift?->name,
                    'regu' => $b->regu,
                    'operator' => $b->operator?->name,
                    'is_continuation' => $b->is_continuation,
                    'parent_batch_number' => $b->parent?->batch_number,
                    'process_status' => $b->process_status,
                    'ink_weight' => $b->ink_weight,
                    'recovery_weight' => $b->recovery_weight,
                    'oil_temp' => $b->oil_temp,
                    'vapour_temp' => $b->vapour_temp,
                    'notes' => $b->notes,
                    'is_last' => $lastId === $b->id,
                    'last_batch_number' => $lastNumbers[$lastId] ?? $b->batch_number,
                    'correction' => $b->correctionSummary(),
                ];
            })->values(),
            'meta' => Correction::pageMeta($page) + [
                'shifts' => Shift::orderBy('id')->get(['id', 'name', 'is_active']),
                'temperature' => config('reclaimer.temperature'),
            ],
        ]);
    }

    public function update(Request $request, ReclaimerBatch $batch, CorrectBatch $correct): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'regu' => ['nullable', 'string', 'max:20'],
            'process_status' => ['required', 'in:in_progress,completed'],
            'recovery_weight' => ['required', 'numeric', 'min:0', 'max:99999'],
            'oil_temp' => ['required', 'numeric', 'between:0,400'],
            'vapour_temp' => ['required', 'numeric', 'between:0,400'],
            'notes' => ['nullable', 'string', 'max:500'],
        ] + Correction::reasonRules(), (new StoreBatchRequest)->messages() + Correction::reasonMessages());

        $message = $correct->update($request, $batch, [
            'date' => $data['date'],
            'shift_id' => (int) $data['shift_id'],
            'regu' => filled($data['regu'] ?? null) ? trim($data['regu']) : null,
            'process_status' => $data['process_status'],
            'recovery_weight' => round((float) $data['recovery_weight'], 2),
            'oil_temp' => round((float) $data['oil_temp'], 2),
            'vapour_temp' => round((float) $data['vapour_temp'], 2),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ], trim($data['reason']));

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, ReclaimerBatch $batch, CorrectBatch $correct): JsonResponse
    {
        $message = $correct->delete($request, $batch, Correction::reason($request, deleting: true));

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
