<?php

namespace App\Http\Controllers\Api\Correction;

use App\Actions\Corrections\CorrectMaintenance;
use App\Http\Controllers\Controller;
use App\Models\MachineMaintenanceLog;
use App\Support\Correction;
use App\Support\MaintenanceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Koreksi data: periode maintenance mesin (log mulai dan log selesai). */
class MaintenanceCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request);
        $term = mb_strtolower(trim($filters['q'] ?? ''));

        $windows = MaintenanceHistory::windows()->reverse()->values()
            ->filter(fn (array $w) => empty($filters['from']) || $w['start']->toDateString() >= $filters['from'])
            ->filter(fn (array $w) => empty($filters['to']) || $w['start']->toDateString() <= $filters['to'])
            ->filter(fn (array $w) => $term === '' || str_contains(mb_strtolower($w['reason'] . ' ' . $w['end_note'] . ' ' . $w['started_by']), $term))
            ->values();

        $current = (int) ($filters['page'] ?? 1);
        $page = new LengthAwarePaginator($windows->forPage($current, 20)->values(), $windows->count(), 20, $current);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(fn (array $w) => MaintenanceHistory::present($w) + [
                // Untuk input datetime-local di form koreksi, dalam zona waktu aplikasi
                'start_local' => $w['start']->format('Y-m-d\TH:i'),
                'end_local' => $w['end']?->format('Y-m-d\TH:i'),
            ])->values(),
            'meta' => Correction::pageMeta($page) + ['now_local' => now()->format('Y-m-d\TH:i')],
        ]);
    }

    public function update(Request $request, MachineMaintenanceLog $log, CorrectMaintenance $correct): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d\TH:i'],
            'end' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'maintenance_reason' => ['required', 'string', 'max:255'],
            'end_note' => ['nullable', 'string', 'max:255'],
        ] + Correction::reasonRules(), [
            'start.required' => 'Isi waktu mulai maintenance.',
            'start.date_format' => 'Waktu mulai tidak valid.',
            'end.date_format' => 'Waktu selesai tidak valid.',
            'maintenance_reason.required' => 'Tulis alasan maintenance, misalnya "Pompa vakum bocor".',
            'maintenance_reason.max' => 'Alasan maintenance paling panjang 255 karakter.',
            'end_note.max' => 'Catatan perbaikan paling panjang 255 karakter.',
        ] + Correction::reasonMessages());

        $message = $correct->update($request, $log, [
            'start' => $data['start'],
            'end' => $data['end'] ?? null,
            'reason' => trim($data['maintenance_reason']),
            'end_note' => filled($data['end_note'] ?? null) ? trim($data['end_note']) : null,
        ], trim($data['reason']));

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, MachineMaintenanceLog $log, CorrectMaintenance $correct): JsonResponse
    {
        $message = $correct->delete($request, $log, Correction::reason($request, deleting: true));

        return response()->json(['status' => 'success', 'message' => $message]);
    }
}
