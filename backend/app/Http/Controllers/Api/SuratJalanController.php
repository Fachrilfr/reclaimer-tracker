<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordSuratJalan;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuratJalanRequest;
use App\Models\B3Pallet;
use App\Models\Parameter;
use App\Models\SuratJalan;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuratJalanController extends Controller
{
    /** Data form surat jalan: vendor aktif dan pallet yang tersedia di TPS. */
    public function create(): JsonResponse
    {
        $thresholds = PalletController::thresholds();
        $available = B3Pallet::with('drums')->where('status', '!=', 'transported')->whereNotNull('sent_to_tps_at')
            ->orderBy('expiry_date')->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => now()->toDateString(),
                'min_pallets' => max(1, (int) Parameter::number('min_pallet_per_delivery')),
                'drums_per_pallet' => (int) Parameter::number('drums_per_pallet'),
                'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'pic_name']),
                'pallets' => $available->map(fn (B3Pallet $p) => PalletController::present($p, $thresholds))->values(),
            ],
        ]);
    }

    public function store(StoreSuratJalanRequest $request, RecordSuratJalan $record): JsonResponse
    {
        $suratJalan = $record->handle($request, $request->validated(), $request->file('file'))
            ->load(['vendor', 'pallets', 'creator']);

        return response()->json([
            'status' => 'success',
            'message' => "Surat jalan {$suratJalan->surat_jalan_number} tersimpan.",
            'data' => $suratJalan->present(),
        ], 201);
    }

    /** Arsip surat jalan dengan pencarian (FR-40). */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'vendor_id' => ['nullable', 'integer'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);

        $query = SuratJalan::with(['vendor', 'pallets', 'creator'])
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $q->where(fn (Builder $w) => $w->where('surat_jalan_number', 'like', $like)
                    ->orWhere('file_name', 'like', $like)
                    ->orWhere('vendor_officer_name', 'like', $like)
                    ->orWhereHas('pallets', fn (Builder $p) => $p->where('pallet_number', 'like', $like))
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', $like)));
            })
            ->when($filters['vendor_id'] ?? null, fn (Builder $q, $id) => $q->where('vendor_id', $id))
            ->when($filters['bulan'] ?? null, fn (Builder $q, string $month) => $q
                ->whereDate('handover_date', '>=', "{$month}-01")
                ->whereDate('handover_date', '<=', date('Y-m-t', strtotime("{$month}-01"))))
            ->orderByDesc('handover_date')->orderByDesc('id');

        $page = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(fn (SuratJalan $s) => $s->present())->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'vendors' => Vendor::withTrashed()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Bukti surat jalan sebagai JSON base64: pengelola unduhan seperti IDM mencegat
     * respons PDF biasa di browser, tetapi tidak menyentuh JSON.
     */
    public function file(SuratJalan $suratJalan): JsonResponse
    {
        $disk = Storage::disk(RecordSuratJalan::DISK);
        abort_unless($disk->exists($suratJalan->file_path_surat_jalan), 404, 'Berkas bukti tidak ditemukan di server.');

        return response()->json(['status' => 'success', 'data' => [
            'filename' => $suratJalan->file_name ?? basename($suratJalan->file_path_surat_jalan),
            'content_type' => $suratJalan->file_mime ?? 'application/octet-stream',
            'content' => base64_encode($disk->get($suratJalan->file_path_surat_jalan)),
        ]]);
    }
}
