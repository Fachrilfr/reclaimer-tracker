<?php

namespace App\Http\Controllers\Api\Correction;

use App\Actions\Corrections\CorrectSolventUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolventUsageRequest;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use App\Support\Correction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Koreksi data: pemakaian solvent. */
class SolventCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request);

        $page = SolventUsage::with(['shift', 'user', 'corrector'])
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('date', '<=', $to))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters) {
                $like = Correction::like($filters['q']);
                $q->where(fn (Builder $w) => $w->where('purpose', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)));
            })
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(fn (SolventUsage $u) => $u->present())->values(),
            'meta' => Correction::pageMeta($page) + [
                'shifts' => Shift::orderBy('id')->get(['id', 'name', 'is_active']),
                'balance' => SolventLedger::currentBalance(),
            ],
        ]);
    }

    public function update(Request $request, SolventUsage $usage, CorrectSolventUsage $correct): JsonResponse
    {
        $data = $request->validate((new StoreSolventUsageRequest)->rules() + Correction::reasonRules(),
            (new StoreSolventUsageRequest)->messages() + Correction::reasonMessages());

        $notes = trim((string) ($data['notes'] ?? ''));
        $message = $correct->update($request, $usage, [
            'date' => $data['date'],
            'shift_id' => (int) $data['shift_id'],
            'weight' => round((float) $data['weight'], 2),
            'purpose' => trim($data['purpose']),
            'notes' => $notes === '' ? null : $notes,
        ], trim($data['reason']));

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, SolventUsage $usage, CorrectSolventUsage $correct): JsonResponse
    {
        $message = $correct->delete($request, $usage, Correction::reason($request, deleting: true));

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
