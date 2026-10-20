<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReclaimerBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChainReviewController extends Controller
{
    /**
     * Supervisor meninjau rangkaian di bawah target (hak Tinjau dashboard, PRD §5.1).
     * Rangkaian yang sudah ditinjau hilang dari "Perlu ditindak"; yang tidak ditinjau
     * tetap hilang sendiri 14 hari setelah selesai.
     */
    public function store(Request $request, ReclaimerBatch $batch): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ], [
            'notes.required' => 'Tulis catatan tindak lanjut, misalnya "Suhu uap terlalu rendah, operator sudah diarahkan".',
            'notes.max' => 'Catatan paling panjang 500 karakter.',
        ]);

        $root = DB::transaction(function () use ($request, $batch, $data) {
            $root = ReclaimerBatch::lockForUpdate()->findOrFail($batch->root()->id);

            $problem = match (true) {
                $root->chain_completed_at === null => 'Rangkaian ini belum selesai, jadi belum dinilai terhadap target.',
                ! $root->is_recovery_low => 'Rangkaian ini memenuhi target recovery, tidak perlu ditinjau.',
                (bool) $root->supervisor_reviewed => "Rangkaian {$root->batch_number} sudah ditinjau.",
                default => null,
            };
            if ($problem) {
                throw ValidationException::withMessages(['notes' => $problem]);
            }

            $root->update([
                'supervisor_reviewed' => true,
                'supervisor_notes' => trim($data['notes']),
                'supervisor_reviewed_by' => $request->user()->id,
                'supervisor_reviewed_at' => now(),
            ]);
            AuditLog::record($request, 'review', $root, [
                'batch_number' => $root->batch_number,
                'recovery_rate' => $root->chain_recovery_rate,
                'notes' => $root->supervisor_notes,
            ]);

            return $root;
        });

        return response()->json([
            'status' => 'success',
            'message' => "Rangkaian {$root->batch_number} sudah ditinjau.",
            'data' => $root->load('reviewer')->reviewSummary(),
        ]);
    }
}
