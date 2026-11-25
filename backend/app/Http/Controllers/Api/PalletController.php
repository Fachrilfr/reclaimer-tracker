<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordPallet;
use App\Http\Controllers\Controller;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\Parameter;
use App\Support\StorageStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PalletController extends Controller
{
    /** Pallet yang sedang diisi beserta drumnya, dan pallet terbaru (FR-25, FR-26). */
    public function index(): JsonResponse
    {
        $thresholds = self::thresholds();

        $filling = B3Pallet::with('drums.operator')->where('status', 'filling')->first();
        $recent = B3Pallet::with(['drums', 'suratJalan'])->orderByDesc('start_date')->orderByDesc('id')->limit(10)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => now()->toDateString(),
                'machine' => Machine::main()?->load('reporter')->summary(),
                'drums_per_pallet' => (int) Parameter::number('drums_per_pallet'),
                'max_storage_days' => (int) Parameter::number('b3_max_storage_days'),
                'filling' => $filling ? self::present($filling, $thresholds, withDrums: true) : null,
                'recent' => $recent->map(fn (B3Pallet $pallet) => self::present($pallet, $thresholds)),
            ],
        ]);
    }

    /** Mulai mengisi pallet baru; waktu mulai diambil saat tombol ditekan. */
    public function store(Request $request, RecordPallet $recordPallet): JsonResponse
    {
        $pallet = $recordPallet->start($request, $this->notes($request));

        return response()->json([
            'status' => 'success',
            'message' => "Pallet {$pallet->pallet_number} mulai diisi.",
            'data' => self::present($pallet, self::thresholds()),
        ], 201);
    }

    /** Pallet penuh saat tombol ditekan, langsung tercatat terkirim ke TPS. */
    public function markFull(Request $request, B3Pallet $pallet, RecordPallet $recordPallet): JsonResponse
    {
        $pallet = $recordPallet->markFull($request, $pallet, $this->notes($request));

        return response()->json([
            'status' => 'success',
            'message' => "Pallet {$pallet->pallet_number} penuh dan terkirim ke TPS.",
            'data' => self::present($pallet->load('drums'), self::thresholds()),
        ]);
    }

    public function dispatchToTps(Request $request, RecordPallet $recordPallet): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sent_to_tps_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'pallet_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'sent_to_tps_at.after_or_equal' => 'Tanggal pengiriman tidak boleh sebelum tanggal mulai pengisian.',
            'sent_to_tps_at.before_or_equal' => 'Tanggal pengiriman tidak boleh setelah hari ini.',
            'start_date.before_or_equal' => 'Tanggal pengisian tidak boleh setelah hari ini.',
        ]);
        if (isset($data['pallet_id'])) {
            $data['pallet_id'] = (int) $data['pallet_id'];
        }
        $pallet = $recordPallet->dispatchToTps($request, $data);

        return response()->json([
            'status' => 'success',
            'message' => "Pengiriman pallet {$pallet->pallet_number} ke TPS sudah tercatat.",
            'data' => self::present($pallet, self::thresholds()),
        ], 201);
    }

    private function notes(Request $request): ?string
    {
        $notes = $request->validate(
            ['notes' => ['nullable', 'string', 'max:500']],
            ['notes.max' => 'Keterangan paling panjang 500 karakter.'],
        )['notes'] ?? null;

        return filled($notes) ? trim($notes) : null;
    }

    /** Ambang masa simpan, diambil sekali per request. */
    public static function thresholds(): array
    {
        return [
            'storage' => StorageStatus::thresholds(),
        ];
    }

    public static function present(B3Pallet $pallet, array $thresholds, bool $withDrums = false): array
    {
        $data = [
            'id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'status' => $pallet->status,
            'drum_count' => $pallet->drum_count,
            'available_drums' => $pallet->remainingDrums(),
            'start_date' => $pallet->start_date->toDateString(),
            'full_date' => $pallet->full_date?->toDateString(),
            // Waktu tombol ditekan: mulai diisi dan penuh (null untuk data lama tanpa jam)
            'started_at' => ($pallet->status === 'filling' || $pallet->full_at) && $pallet->created_at?->isSameDay($pallet->start_date)
                ? $pallet->created_at->toIso8601String() : null,
            'full_at' => $pallet->full_at?->toIso8601String(),
            // Total hari kirim ke TPS: tanggal penuh dan terkirim dikurangi tanggal mulai diisi.
            // Null selama pallet masih diisi.
            'fill_days' => $pallet->sent_to_tps_at
                ? (int) round($pallet->start_date->copy()->startOfDay()->diffInDays($pallet->sent_to_tps_at->copy()->startOfDay()))
                : null,
            'sent_to_tps_at' => $pallet->sent_to_tps_at?->toDateString(),
            // Batas simpan baru ada setelah pallet sampai di TPS
            'expiry_date' => $pallet->expiry_date?->toDateString(),
            // Masa simpan di TPS sampai ke vendor (90 hari); tidak ada batas kirim ke TPS
            'storage' => $pallet->storageStatus($thresholds['storage'])->toArray(),
            // Keterangan saat mulai pallet dan saat mencatat drum, untuk riwayat pallet
            'notes' => $pallet->notes,
            'drum_notes' => $pallet->relationLoaded('drums')
                ? $pallet->drums->filter(fn ($drum) => filled($drum->notes))
                    ->map(fn ($drum) => ['sequence' => $drum->drum_sequence, 'notes' => $drum->notes])
                    ->values()
                : [],
            'correction' => $pallet->correctionSummary(),
        ];

        if ($withDrums) {
            $data['drums'] = $pallet->drums->map(fn ($drum) => [
                'sequence' => $drum->drum_sequence,
                'filled_date' => $drum->filled_date->toDateString(),
                'operator' => $drum->operator?->name,
                'notes' => $drum->notes,
            ]);
        }

        return $data;
    }
}
