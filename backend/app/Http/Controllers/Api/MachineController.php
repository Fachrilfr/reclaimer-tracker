<?php

namespace App\Http\Controllers\Api;

use App\Actions\SetMachineStatus;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Support\MaintenanceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    /** Status mesin dan riwayat maintenance, terbaru dulu. */
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'machine' => Machine::main()?->load('reporter')->summary(),
                'history' => MaintenanceHistory::windows()->reverse()->values()
                    ->map(fn (array $w) => MaintenanceHistory::present($w)),
            ],
        ]);
    }

    public function startMaintenance(Request $request, SetMachineStatus $setStatus): JsonResponse
    {
        $data = $request->validate([
            'started_at' => ['required_with:ended_at', 'nullable', 'date', 'before_or_equal:now'],
            'ended_at' => ['nullable', 'date', 'after:started_at', 'before_or_equal:now'],
            'note' => ['required_with:ended_at', 'nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Tulis alasan maintenance, misalnya "Pompa vakum bocor".',
            'reason.max' => 'Alasan paling panjang 255 karakter.',
        ]);

        $start = $request->date('started_at') ?? now();
        if ($end = $request->date('ended_at')) {
            $machine = $setStatus->recordCompleted($request, $start, $end, trim($data['reason']), trim($data['note']));

            return response()->json(['status' => 'success', 'message' => 'Riwayat maintenance tersimpan.', 'data' => $machine->summary()]);
        }
        $machine = $setStatus->startMaintenance($request, trim($data['reason']), $start);

        return response()->json([
            'status' => 'success',
            'message' => "Mesin {$machine->code} dilaporkan maintenance. HSE, supervisor, manajemen, dan super admin sudah diberi tahu.",
            'data' => $machine->load('reporter')->summary(),
        ]);
    }

    public function endMaintenance(Request $request, SetMachineStatus $setStatus): JsonResponse
    {
        $data = $request->validate([
            'ended_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'Tulis catatan perbaikan, misalnya "Seal pompa diganti".',
            'note.max' => 'Catatan paling panjang 255 karakter.',
        ]);

        $machine = $setStatus->endMaintenance($request, trim($data['note']), $request->date('ended_at') ?? now());

        return response()->json([
            'status' => 'success',
            'message' => "Mesin {$machine->code} kembali beroperasi. Pemberitahuan sudah dikirim.",
            'data' => $machine->summary(),
        ]);
    }
}
