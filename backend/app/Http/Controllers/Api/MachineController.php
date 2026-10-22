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
            'reason' => ['required', 'string', 'max:255'],
            'expected_end_at' => ['nullable', 'date', 'after:now'],
        ], [
            'reason.required' => 'Tulis alasan maintenance, misalnya "Pompa vakum bocor".',
            'reason.max' => 'Alasan paling panjang 255 karakter.',
            'expected_end_at.date' => 'Perkiraan selesai tidak valid.',
            'expected_end_at.after' => 'Perkiraan selesai harus setelah waktu sekarang.',
        ]);

        $machine = $setStatus->startMaintenance($request, trim($data['reason']), $data['expected_end_at'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => "Mesin {$machine->code} dilaporkan maintenance. HSE, supervisor, manajemen, dan super admin sudah diberi tahu.",
            'data' => $machine->load('reporter')->summary(),
        ]);
    }

    public function endMaintenance(Request $request, SetMachineStatus $setStatus): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'Tulis catatan perbaikan, misalnya "Seal pompa diganti".',
            'note.max' => 'Catatan paling panjang 255 karakter.',
        ]);

        $machine = $setStatus->endMaintenance($request, trim($data['note']));

        return response()->json([
            'status' => 'success',
            'message' => "Mesin {$machine->code} kembali beroperasi. Pemberitahuan sudah dikirim.",
            'data' => $machine->summary(),
        ]);
    }
}
