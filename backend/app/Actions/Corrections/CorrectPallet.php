<?php

namespace App\Actions\Corrections;

use App\Models\B3Pallet;
use App\Models\Machine;
use App\Support\Correction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi dan hapus pallet residu. Waktu mulai dan penuh diambil saat tombol ditekan,
 * jadi yang biasanya salah adalah tombol yang telat atau keliru ditekan:
 * - tanggal mulai diisi dan tanggal penuh (sama dengan tanggal sampai di TPS);
 *   batas simpan ikut dihitung ulang dari tanggal sampai di TPS;
 * - status penuh yang keliru ditekan bisa dikembalikan menjadi masih diisi;
 * - jumlah drum bila isi pallet sebenarnya tidak sesuai.
 * Pallet yang sudah diangkut dengan surat jalan tidak bisa dihapus.
 */
class CorrectPallet
{
    /** @param  array{status: string, start_date: string, full_date: ?string, drum_count: ?int, notes: ?string}  $data */
    public function update(Request $request, B3Pallet $pallet, array $data, string $reason): string
    {
        return DB::transaction(function () use ($request, $pallet, $data, $reason) {
            // Kunci yang sama dengan RecordPallet::start: satu pallet diisi dalam satu waktu
            Machine::orderBy('id')->lockForUpdate()->first();
            $pallet = B3Pallet::lockForUpdate()->findOrFail($pallet->id);
            $transported = $pallet->status === 'transported';
            $filling = ! $transported && $data['status'] === 'filling';

            if ($data['start_date'] > now()->toDateString()) {
                throw ValidationException::withMessages(['start_date' => 'Tanggal mulai diisi tidak boleh setelah hari ini.']);
            }

            if ($filling) {
                $other = B3Pallet::where('status', 'filling')->whereKeyNot($pallet->id)->first();
                if ($other) {
                    throw ValidationException::withMessages([
                        'status' => "Pallet {$other->pallet_number} sedang diisi. Hanya satu pallet yang bisa diisi dalam satu waktu.",
                    ]);
                }
                $data = ['full_date' => null, 'drum_count' => 0] + $data;
            } else {
                $this->ensureFullDate($pallet, $data, $transported);
            }

            $values = [
                'status' => $transported ? 'transported' : $data['status'],
                'start_date' => $data['start_date'],
                'full_date' => $data['full_date'],
                'drum_count' => (int) $data['drum_count'],
                'notes' => $data['notes'],
            ];
            [$old, $new] = Correction::diff($this->snapshot($pallet), $values);
            if (! $new) {
                throw Correction::nothingChanged();
            }

            $this->apply($pallet, $values);
            Correction::mark($pallet, $request, $reason);
            $pallet->save();

            Correction::audit($request, 'correct', $pallet, $pallet->pallet_number, $reason, $old, $new);

            return "Pallet {$pallet->pallet_number} dikoreksi."
                . (isset($new['full_date']) && $pallet->expiry_date ? ' Batas simpan sekarang ' . Correction::date($pallet->expiry_date) . '.' : '');
        });
    }

    public function delete(Request $request, B3Pallet $pallet, string $reason): string
    {
        return DB::transaction(function () use ($request, $pallet, $reason) {
            $pallet = B3Pallet::lockForUpdate()->findOrFail($pallet->id);

            if ($pallet->status === 'transported') {
                $number = $pallet->suratJalan()->value('surat_jalan_number');
                throw ValidationException::withMessages([
                    'reason' => "Pallet ini sudah diangkut dengan surat jalan {$number}. Keluarkan pallet dari surat jalan itu dulu lewat koreksi surat jalan.",
                ]);
            }

            $snapshot = $this->snapshot($pallet);
            $pallet->delete();
            Correction::audit($request, 'delete', $pallet, $pallet->pallet_number, $reason, $snapshot);

            return "Pallet {$pallet->pallet_number} dihapus.";
        });
    }

    private function ensureFullDate(B3Pallet $pallet, array $data, bool $transported): void
    {
        $full = $data['full_date'];
        $error = match (true) {
            ! $full => 'Isi tanggal pallet penuh dan sampai di TPS.',
            $full < $data['start_date'] => 'Tanggal penuh tidak boleh sebelum tanggal mulai diisi.',
            $full > now()->toDateString() => 'Tanggal penuh tidak boleh setelah hari ini.',
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['full_date' => $error]);
        }

        if ($transported) {
            $handover = $pallet->suratJalan()->value('handover_date');
            if ($handover && $full > Carbon::parse($handover)->toDateString()) {
                throw ValidationException::withMessages([
                    'full_date' => 'Tanggal sampai di TPS tidak boleh setelah serah terima ke vendor (' . Correction::date($handover) . ').',
                ]);
            }
        }

        if ((int) $data['drum_count'] < 1) {
            throw ValidationException::withMessages(['drum_count' => 'Isi jumlah drum di pallet ini, paling sedikit 1.']);
        }
    }

    /** Terapkan nilai koreksi. Jam saat tombol ditekan dipertahankan, tanggalnya yang diganti. */
    private function apply(B3Pallet $pallet, array $values): void
    {
        if ($values['start_date'] !== $pallet->start_date->toDateString()) {
            $pallet->start_date = $values['start_date'];
            // created_at dipakai sebagai waktu tombol mulai diisi ditekan
            $pallet->created_at = Carbon::parse($values['start_date'])->setTimeFrom($pallet->created_at ?? now());
        }

        $pallet->fill(['status' => $values['status'], 'drum_count' => $values['drum_count'], 'notes' => $values['notes']]);

        if ($values['full_date'] === null) {
            $pallet->fill(['full_date' => null, 'full_at' => null, 'sent_to_tps_at' => null, 'expiry_date' => null]);

            return;
        }

        $full = Carbon::parse($values['full_date'])->startOfDay();
        $pallet->fill([
            'full_date' => $full,
            'full_at' => $full->copy()->setTimeFrom($pallet->full_at ?? now()),
            'sent_to_tps_at' => $full,
            'expiry_date' => $full->copy()->addDays($pallet->max_storage_days),
        ]);
    }

    private function snapshot(B3Pallet $pallet): array
    {
        return [
            'status' => $pallet->status,
            'start_date' => $pallet->start_date->toDateString(),
            'full_date' => $pallet->full_date?->toDateString(),
            'drum_count' => (int) $pallet->drum_count,
            'notes' => $pallet->notes,
        ];
    }
}
