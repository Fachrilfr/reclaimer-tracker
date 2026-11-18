<?php

namespace App\Http\Controllers\Api\Correction;

use App\Actions\Corrections\CorrectPallet;
use App\Http\Controllers\Api\PalletController;
use App\Http\Controllers\Controller;
use App\Models\B3Pallet;
use App\Models\Parameter;
use App\Support\Correction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Koreksi data: pallet residu. */
class PalletCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request);
        $thresholds = PalletController::thresholds();

        $page = B3Pallet::with(['suratJalan', 'corrector'])
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('start_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('start_date', '<=', $to))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters) {
                $like = Correction::like($filters['q']);
                $q->where(fn (Builder $w) => $w->where('pallet_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('suratJalan', fn (Builder $s) => $s->where('surat_jalan_number', 'like', $like)));
            })
            ->orderByDesc('start_date')->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(function (B3Pallet $p) use ($thresholds) {
                $suratJalan = $p->suratJalan->first();

                return PalletController::present($p, $thresholds) + [
                    'surat_jalan_number' => $suratJalan?->surat_jalan_number,
                    'handover_date' => $suratJalan?->handover_date->toDateString(),
                ];
            })->values(),
            'meta' => Correction::pageMeta($page) + [
                'drums_per_pallet' => (int) Parameter::number('drums_per_pallet'),
                'max_storage_days' => (int) Parameter::number('b3_max_storage_days'),
            ],
        ]);
    }

    public function update(Request $request, B3Pallet $pallet, CorrectPallet $correct): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:filling,full'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'full_date' => ['nullable', 'date_format:Y-m-d'],
            'drum_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ] + Correction::reasonRules(), [
            'status.in' => 'Status pallet tidak dikenal. Pilih masih diisi atau penuh.',
            'start_date.required' => 'Isi tanggal mulai diisi.',
            'start_date.date_format' => 'Tanggal mulai diisi tidak valid.',
            'full_date.date_format' => 'Tanggal penuh tidak valid.',
            'drum_count.integer' => 'Jumlah drum berupa bilangan bulat.',
            'drum_count.max' => 'Jumlah drum paling banyak 20.',
            'notes.max' => 'Keterangan paling panjang 500 karakter.',
        ] + Correction::reasonMessages());

        $message = $correct->update($request, $pallet, [
            'status' => $data['status'] ?? ($pallet->status === 'filling' ? 'filling' : 'full'),
            'start_date' => $data['start_date'],
            'full_date' => $data['full_date'] ?? null,
            'drum_count' => isset($data['drum_count']) ? (int) $data['drum_count'] : 0,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ], trim($data['reason']));

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, B3Pallet $pallet, CorrectPallet $correct): JsonResponse
    {
        $message = $correct->delete($request, $pallet, Correction::reason($request, deleting: true));

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
