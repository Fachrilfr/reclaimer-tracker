<?php

namespace App\Http\Controllers\Api\Correction;

use App\Actions\Corrections\CorrectSuratJalan;
use App\Http\Controllers\Api\PalletController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuratJalanRequest;
use App\Models\B3Pallet;
use App\Models\Parameter;
use App\Models\SuratJalan;
use App\Models\Vendor;
use App\Support\Correction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Koreksi data: surat jalan. Disimpan lewat POST karena bisa membawa berkas bukti baru. */
class SuratJalanCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request);
        $thresholds = PalletController::thresholds();

        $page = SuratJalan::with(['vendor', 'pallets', 'creator', 'corrector'])
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('handover_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('handover_date', '<=', $to))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters) {
                $like = Correction::like($filters['q']);
                $q->where(fn (Builder $w) => $w->where('surat_jalan_number', 'like', $like)
                    ->orWhere('vendor_officer_name', 'like', $like)
                    ->orWhereHas('pallets', fn (Builder $p) => $p->where('pallet_number', 'like', $like))
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', $like)));
            })
            ->orderByDesc('handover_date')->orderByDesc('id')
            ->paginate(20);

        // Pallet yang bisa ditambahkan ke surat jalan: yang sedang di TPS
        $available = B3Pallet::with('suratJalan')->where('status', '!=', 'transported')->whereNotNull('sent_to_tps_at')
            ->orderBy('sent_to_tps_at')->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(fn (SuratJalan $s) => $s->present() + [
                'pallet_tps_dates' => $s->pallets->mapWithKeys(fn (B3Pallet $p) => [$p->id => $p->sent_to_tps_at?->toDateString()]),
            ])->values(),
            'meta' => Correction::pageMeta($page) + [
                'vendors' => Vendor::withTrashed()->orderBy('name')->get(['id', 'name', 'is_active', 'deleted_at'])
                    ->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name, 'selectable' => $v->is_active && ! $v->deleted_at]),
                'available_pallets' => $available->map(fn (B3Pallet $p) => PalletController::present($p, $thresholds))->values(),
                'min_pallets' => 1,
            ],
        ]);
    }

    public function update(Request $request, SuratJalan $suratJalan, CorrectSuratJalan $correct): JsonResponse
    {
        $store = new StoreSuratJalanRequest;
        $rules = $store->rules();
        $rules['surat_jalan_number'] = ['required', 'string', 'max:100', Rule::unique('surat_jalan', 'surat_jalan_number')->ignore($suratJalan->id)];
        // Vendor yang sudah nonaktif tetap boleh dipertahankan, tetapi tidak boleh dipilih baru
        $rules['vendor_id'] = ['required', 'integer', function ($attribute, $value, $fail) use ($suratJalan) {
            $ok = (int) $value === (int) $suratJalan->vendor_id
                || Vendor::whereKey($value)->where('is_active', true)->exists();
            if (! $ok) {
                $fail('Vendor tidak ditemukan atau tidak aktif. Pilih ulang vendor.');
            }
        }];

        $data = $request->validate($rules + Correction::reasonRules(), $store->messages() + Correction::reasonMessages());

        $message = $correct->update($request, $suratJalan, $data, $request->file('file'), trim($data['reason']));

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, SuratJalan $suratJalan, CorrectSuratJalan $correct): JsonResponse
    {
        $message = $correct->delete($request, $suratJalan, Correction::reason($request, deleting: true));

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
