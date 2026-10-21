<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\B3NotificationLog;
use App\Models\B3Pallet;
use App\Models\Parameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Limbah B3 untuk HSE: inventaris pallet dari input drum operator, status kirim ke
 * TPS, masa simpan sampai diserahkan ke vendor, dan log pengingat (FR-25 s/d FR-30).
 */
class WasteController extends Controller
{
    public const LOCATIONS = ['tps', 'reclaimer', 'diserahkan'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'lokasi' => ['nullable', 'in:semua,' . implode(',', self::LOCATIONS)],
            'q' => ['nullable', 'string', 'max:50'],
        ]);
        $location = $filters['lokasi'] ?? 'tps';
        $thresholds = PalletController::thresholds();

        $all = B3Pallet::with(['drums', 'suratJalan.vendor'])->orderByDesc('start_date')->orderByDesc('id')->get();
        $counts = ['semua' => $all->count()] + collect(self::LOCATIONS)->mapWithKeys(fn ($l) => [$l => 0])->all();
        foreach ($all as $pallet) {
            $counts[$pallet->location()]++;
        }

        $rows = $all
            ->when($location !== 'semua', fn ($c) => $c->filter(fn (B3Pallet $p) => $p->location() === $location))
            ->when($filters['q'] ?? null, fn ($c, $q) => $c->filter(fn (B3Pallet $p) => str_contains(strtolower($p->pallet_number), strtolower($q))
                || str_contains(strtolower((string) $p->suratJalan->first()?->surat_jalan_number), strtolower($q))))
            ->map(fn (B3Pallet $p) => self::present($p, $thresholds))
            // Yang paling mendesak di atas: sisa masa simpan paling sedikit
            ->sortBy(fn (array $p) => [$p['location'] === 'diserahkan' ? 1 : 0, $p['storage']['days_remaining'] ?? PHP_INT_MAX])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'meta' => [
                'location' => $location,
                'counts' => $counts,
                'needs_action' => $all->filter(fn (B3Pallet $p) => $p->location() !== 'diserahkan' && $p->storageStatus($thresholds['storage'])->needsAction())->count(),
                'drums_per_pallet' => (int) Parameter::number('drums_per_pallet'),
                'max_storage_days' => (int) Parameter::number('b3_max_storage_days'),
            ],
        ]);
    }

    /** Log pengingat yang terkirim ke HSE (FR-30). */
    public function reminders(): JsonResponse
    {
        $logs = B3NotificationLog::with('pallet')->orderByDesc('sent_at')->orderByDesc('id')->limit(50)->get();
        $levels = array_flip(B3NotificationLog::TYPES);

        return response()->json([
            'status' => 'success',
            'data' => $logs->map(fn (B3NotificationLog $log) => [
                'id' => $log->id,
                'pallet_id' => $log->pallet_id,
                'pallet_number' => $log->pallet?->pallet_number,
                'level' => $levels[$log->notification_type] ?? $log->notification_type,
                'days_remaining' => $log->days_remaining,
                'message' => $log->message,
                'sent_at' => $log->sent_at->toIso8601String(),
            ]),
        ]);
    }

    public static function present(B3Pallet $pallet, array $thresholds): array
    {
        $suratJalan = $pallet->suratJalan->first();

        return PalletController::present($pallet, $thresholds) + [
            'location' => $pallet->location(),
            'surat_jalan' => $suratJalan ? [
                'id' => $suratJalan->id,
                'number' => $suratJalan->surat_jalan_number,
                'handover_date' => $suratJalan->handover_date->toDateString(),
                'vendor' => $suratJalan->vendor?->name,
            ] : null,
        ];
    }
}
