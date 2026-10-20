<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\B3DrumLog;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan pallet limbah B3 oleh operator (FR-25). Residu tidak ditimbang per batch;
 * operator mencatat drum yang penuh, dan pallet penuh setelah berisi `drums_per_pallet` drum.
 * Masa simpan dihitung sejak pallet mulai terisi (PRD §8, menunggu konfirmasi HSE).
 */
class RecordPallet
{
    public function start(Request $request, string $startDate, ?string $notes): B3Pallet
    {
        return DB::transaction(function () use ($request, $startDate, $notes) {
            // Satu mesin, satu pallet yang sedang diisi. Baris mesin jadi kunci agar dua
            // operator tidak membuka pallet baru bersamaan.
            Machine::orderBy('id')->lockForUpdate()->first();

            $filling = B3Pallet::where('status', 'filling')->first();
            if ($filling) {
                throw ValidationException::withMessages([
                    'start_date' => "Pallet {$filling->pallet_number} masih diisi. Catat drum sampai pallet penuh sebelum memulai pallet baru.",
                ]);
            }

            $maxDays = (int) Parameter::number('b3_max_storage_days');
            $start = Carbon::parse($startDate);

            $pallet = B3Pallet::create([
                'pallet_number' => $this->nextPalletNumber($start),
                'drum_count' => 0,
                'start_date' => $start,
                'max_storage_days' => $maxDays,
                'expiry_date' => $start->copy()->addDays($maxDays),
                'status' => 'filling',
                'notes' => $notes,
                'created_by' => $request->user()->id,
            ]);

            AuditLog::record($request, 'create', $pallet, $pallet->only([
                'pallet_number', 'start_date', 'max_storage_days', 'expiry_date',
            ]));

            return $pallet;
        });
    }

    public function addDrum(Request $request, B3Pallet $pallet, string $filledDate, ?string $notes): B3DrumLog
    {
        return DB::transaction(function () use ($request, $pallet, $filledDate, $notes) {
            $pallet = B3Pallet::lockForUpdate()->findOrFail($pallet->id);
            $capacity = (int) Parameter::number('drums_per_pallet');
            $filled = Carbon::parse($filledDate)->startOfDay();

            if ($pallet->status !== 'filling' || $pallet->drum_count >= $capacity) {
                throw ValidationException::withMessages([
                    'filled_date' => "Pallet {$pallet->pallet_number} sudah penuh. Mulai pallet baru untuk drum berikutnya.",
                ]);
            }

            $lastDrumDate = $pallet->drums()->max('filled_date');
            $earliest = $lastDrumDate ? Carbon::parse($lastDrumDate) : $pallet->start_date;
            if ($filled->lt($earliest)) {
                throw ValidationException::withMessages([
                    'filled_date' => 'Tanggal drum penuh tidak boleh sebelum ' . $earliest->locale('id')->translatedFormat('j M Y') . '.',
                ]);
            }

            $drum = B3DrumLog::create([
                'pallet_id' => $pallet->id,
                'drum_sequence' => $pallet->drum_count + 1,
                'filled_date' => $filled,
                'operator_id' => $request->user()->id,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $isFull = $drum->drum_sequence >= $capacity;
            $pallet->update([
                'drum_count' => $drum->drum_sequence,
                'status' => $isFull ? 'full' : 'filling',
                'full_date' => $isFull ? $filled : null,
            ]);

            AuditLog::record($request, 'create', $drum, [
                'pallet_number' => $pallet->pallet_number,
                'drum_sequence' => $drum->drum_sequence,
                'filled_date' => $filled->toDateString(),
                'pallet_full' => $isFull,
            ]);

            return $drum;
        });
    }

    private function nextPalletNumber(Carbon $start): string
    {
        $prefix = 'PLT-' . $start->format('Ym') . '-';
        $last = B3Pallet::withTrashed()->where('pallet_number', 'like', $prefix . '%')->max('pallet_number');
        $sequence = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
