<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordSolventUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSolventUsageRequest;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use Illuminate\Http\JsonResponse;

class SolventUsageController extends Controller
{
    /** Riwayat pemakaian solvent terbaru, dengan saldo saat ini dan data pendukung form. */
    public function index(): JsonResponse
    {
        $page = SolventUsage::with(['shift', 'user'])->orderByDesc('date')->orderByDesc('id')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(fn (SolventUsage $u) => $u->present())->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'balance' => SolventLedger::currentBalance(),
                'today' => now()->toDateString(),
                'shifts' => Shift::where('is_active', true)->orderBy('id')->get(['id', 'name']),
                'suggested_shift_id' => Shift::current(now())?->id,
            ],
        ]);
    }

    public function store(StoreSolventUsageRequest $request, RecordSolventUsage $record): JsonResponse
    {
        $usage = $record->handle($request, $request->usageData())->load(['shift', 'user']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pemakaian solvent tersimpan.',
            'data' => $usage->present(),
        ], 201);
    }
}
