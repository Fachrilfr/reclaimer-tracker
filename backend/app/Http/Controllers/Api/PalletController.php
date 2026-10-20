<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordPallet;
use App\Http\Controllers\Controller;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\Parameter;
use App\Support\StorageStatus;
use App\Support\MaintenanceHistory;
use App\Support\TpsDeliveryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PalletController extends Controller
{
    /** Pallet yang sedang diisi beserta drumnya, dan pallet terbaru (FR-25, FR-26). */
    public function index(): JsonResponse
    {
        $thresholds = self::thresholds();

        $filling = B3Pallet::with('drums.operator')->where('status', 'filling')->first();
        $recent = B3Pallet::orderByDesc('start_date')->orderByDesc('id')->limit(10)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => now()->toDateString(),
                'machine' => Machine::main()?->load('reporter')->summary(),
                'drums_per_pallet' => (int) Parameter::number('drums_per_pallet'),
                'max_storage_days' => (int) Parameter::number('b3_max_storage_days'),
                'tps_delivery_days' => (int) Parameter::number('tps_delivery_days'),
                'filling' => $filling ? self::present($filling, $thresholds, withDrums: true) : null,
                'recent' => $recent->map(fn (B3Pallet $pallet) => self::present($pallet, $thresholds)),
            ],
        ]);
    }

    public function store(Request $request, RecordPallet $recordPallet): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'start_date.required' => 'Isi tanggal pallet mulai terisi.',
            'start_date.date_format' => 'Tanggal tidak valid.',
            'start_date.before_or_equal' => 'Tanggal mulai terisi tidak boleh setelah hari ini.',
            'notes.max' => 'Keterangan paling panjang 500 karakter.',
        ]);

        $pallet = $recordPallet->start($request, $data['start_date'], $data['notes'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => "Pallet {$pallet->pallet_number} dimulai.",
            'data' => self::present($pallet, self::thresholds()),
        ], 201);
    }

    public function storeDrum(Request $request, B3Pallet $pallet, RecordPallet $recordPallet): JsonResponse
    {
        $data = $request->validate([
            'filled_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'filled_date.required' => 'Isi tanggal drum penuh.',
            'filled_date.date_format' => 'Tanggal tidak valid.',
            'filled_date.before_or_equal' => 'Tanggal drum penuh tidak boleh setelah hari ini.',
            'notes.max' => 'Keterangan paling panjang 500 karakter.',
        ]);

        $drum = $recordPallet->addDrum($request, $pallet, $data['filled_date'], $data['notes'] ?? null);
        $pallet->refresh();

        return response()->json([
            'status' => 'success',
            'message' => $pallet->status === 'full'
                ? "Drum ke-{$drum->drum_sequence} tercatat. Pallet {$pallet->pallet_number} penuh dan dikirim ke TPS."
                : "Drum ke-{$drum->drum_sequence} tercatat di pallet {$pallet->pallet_number}.",
            'data' => self::present($pallet->load('drums.operator'), self::thresholds(), withDrums: true),
        ], 201);
    }

    /** Ambang kedua status dan periode maintenance, diambil sekali per request. */
    public static function thresholds(): array
    {
        return [
            'storage' => StorageStatus::thresholds(),
            'tps' => TpsDeliveryStatus::thresholds(),
            'maintenance' => MaintenanceHistory::windows(),
        ];
    }

    public static function present(B3Pallet $pallet, array $thresholds, bool $withDrums = false): array
    {
        $data = [
            'id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'status' => $pallet->status,
            'drum_count' => $pallet->drum_count,
            'start_date' => $pallet->start_date->toDateString(),
            'full_date' => $pallet->full_date?->toDateString(),
            'expiry_date' => $pallet->expiry_date->toDateString(),
            'tps_due_date' => $pallet->tps_due_date?->toDateString(),
            // Operator: kirim ke TPS. HSE: masa simpan di TPS sampai ke vendor.
            'tps' => $pallet->tpsStatus($thresholds['tps'], $thresholds['maintenance'] ?? null)->toArray(),
            'storage' => $pallet->storageStatus($thresholds['storage'])->toArray(),
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
