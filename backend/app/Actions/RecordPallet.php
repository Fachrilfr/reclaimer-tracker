<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan pallet limbah B3 oleh operator (FR-25), dengan dua tombol yang mengambil
 * waktu saat ditekan: mulai diisi, lalu penuh. Pallet penuh langsung tercatat terkirim
 * ke TPS dan menjadi tanggung jawab HSE. Drum tidak dicatat satu per satu; pallet penuh
 * dianggap berisi `drums_per_pallet` drum.
 *
 * Masa simpan dihitung sejak pallet sampai di TPS; selama masih diisi di area reclaimer
 * pallet belum punya batas simpan.
 */
class RecordPallet
{
    public function start(Request $request, ?string $notes): B3Pallet
    {
        return DB::transaction(function () use ($request, $notes) {
            // Satu mesin, satu pallet yang sedang diisi. Baris mesin jadi kunci agar dua
            // operator tidak membuka pallet baru bersamaan.
            Machine::orderBy('id')->lockForUpdate()->first();

            $filling = B3Pallet::where('status', 'filling')->first();
            if ($filling) {
                throw ValidationException::withMessages([
                    'pallet' => "Pallet {$filling->pallet_number} masih diisi. Tandai pallet itu penuh sebelum memulai pallet baru.",
                ]);
            }

            $maxDays = (int) Parameter::number('b3_max_storage_days');
            $start = now()->startOfDay();

            $pallet = B3Pallet::create([
                'pallet_number' => $this->nextPalletNumber(),
                'drum_count' => 0,
                'start_date' => $start,
                'max_storage_days' => $maxDays,
                'expiry_date' => null,
                'tps_due_date' => $start->copy()->addDays((int) Parameter::number('tps_delivery_days')),
                'status' => 'filling',
                'notes' => $notes,
                'created_by' => $request->user()->id,
            ]);

            AuditLog::record($request, 'create', $pallet, $pallet->only([
                'pallet_number', 'start_date', 'max_storage_days', 'expiry_date', 'tps_due_date',
            ]) + ['started_at' => $pallet->created_at->toDateTimeString()]);

            return $pallet;
        });
    }

    /** Pallet penuh pada saat tombol ditekan, dan langsung tercatat terkirim ke TPS. */
    public function markFull(Request $request, B3Pallet $pallet, ?string $notes): B3Pallet
    {
        return DB::transaction(function () use ($request, $pallet, $notes) {
            $pallet = B3Pallet::lockForUpdate()->findOrFail($pallet->id);
            if ($pallet->status !== 'filling') {
                throw ValidationException::withMessages([
                    'pallet' => "Pallet {$pallet->pallet_number} sudah penuh dan terkirim ke TPS.",
                ]);
            }

            $now = now();
            $pallet->update([
                'drum_count' => (int) Parameter::number('drums_per_pallet'),
                'status' => 'full',
                'full_date' => $now->copy()->startOfDay(),
                'full_at' => $now,
                // Pallet penuh dikirim ke TPS dan diserahkan ke HSE; masa simpan mulai dihitung
                'sent_to_tps_at' => $now->copy()->startOfDay(),
                'expiry_date' => $now->copy()->startOfDay()->addDays($pallet->max_storage_days),
                'notes' => filled($notes) ? trim(implode("\n", array_filter([$pallet->notes, $notes]))) : $pallet->notes,
            ]);

            AuditLog::record($request, 'update', $pallet, [
                'pallet_number' => $pallet->pallet_number,
                'status' => 'full',
                'full_at' => $now->toDateTimeString(),
                'sent_to_tps' => true,
                'expiry_date' => $pallet->expiry_date->toDateString(),
            ]);

            return $pallet;
        });
    }

    private function nextPalletNumber(): string
    {
        $prefix = 'PLT-' . now()->format('Ym') . '-';
        $last = B3Pallet::withTrashed()->where('pallet_number', 'like', $prefix . '%')->max('pallet_number');
        $sequence = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
