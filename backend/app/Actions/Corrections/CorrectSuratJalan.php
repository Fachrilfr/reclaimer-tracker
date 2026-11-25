<?php

namespace App\Actions\Corrections;

use App\Actions\RecordSuratJalan;
use App\Models\B3Pallet;
use App\Models\SuratJalan;
use App\Models\Vendor;
use App\Support\Correction;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi dan hapus surat jalan: nomor, vendor, tanggal, berat timbangan vendor, petugas,
 * catatan, pallet yang diangkut, dan berkas bukti.
 *
 * Pallet yang dikeluarkan dari surat jalan kembali berstatus di TPS dan masa simpannya
 * berjalan lagi. Surat jalan yang dihapus benar-benar dihapus supaya nomornya bisa
 * dipakai lagi; isinya tetap tercatat lengkap di riwayat koreksi. Berkas bukti lama
 * tidak dihapus dari server, lokasinya ikut dicatat di riwayat koreksi.
 */
class CorrectSuratJalan
{
    /**
     * @param  array{surat_jalan_number: string, vendor_id: int, handover_date: string, weight_kg: float,
     *               vendor_officer_name: string, notes: ?string, pallet_ids: int[]}  $data
     */
    public function update(Request $request, SuratJalan $suratJalan, array $data, ?UploadedFile $file, string $reason): string
    {
        $path = $file?->storeAs('surat-jalan', Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()), RecordSuratJalan::DISK);

        try {
            return DB::transaction(function () use ($request, $suratJalan, $data, $file, $path, $reason) {
                \App\Models\Machine::orderBy('id')->lockForUpdate()->firstOrFail();
            $suratJalan = SuratJalan::with('pallets')->lockForUpdate()->findOrFail($suratJalan->id);
                $currentIds = $suratJalan->pallets->pluck('id')->all();
                $pallets = B3Pallet::whereIn('id', $data['pallet_ids'])->orderBy('pallet_number')->lockForUpdate()->get();
                $this->ensureAvailable($pallets, $data, $currentIds);

                $allocation = RecordSuratJalan::allocate($pallets, $data, $suratJalan);
                $old = $this->snapshot($suratJalan, $suratJalan->pallets);
                $values = [
                    'surat_jalan_number' => trim($data['surat_jalan_number']),
                    'vendor_id' => (int) $data['vendor_id'],
                    'handover_date' => $data['handover_date'],
                    'weight_kg' => round((float) $data['weight_kg'], 2),
                    'vendor_officer_name' => trim($data['vendor_officer_name']),
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                ];
                $newSnapshot = $this->snapshot(new SuratJalan($values + ['file_name' => $file ? $file->getClientOriginalName() : $suratJalan->file_name]), $pallets);
                [$oldChanged, $newChanged] = Correction::diff($old, $newSnapshot);
                if ($file) {
                    // Berkas baru selalu dihitung perubahan walau namanya sama
                    $oldChanged['file_name'] = $suratJalan->file_name;
                    $oldChanged['file_path'] = $suratJalan->file_path_surat_jalan;
                    $newChanged['file_name'] = $file->getClientOriginalName();
                }
                $oldCounts = $suratJalan->pallets->mapWithKeys(fn ($p) => [$p->id => (int) $p->pivot->drum_count])->all();
                $newCounts = array_map(fn ($line) => $line['drum_count'], $allocation);
                if ($oldCounts != $newCounts) {
                    $oldChanged['drum_counts'] = $oldCounts;
                    $newChanged['drum_counts'] = $newCounts;
                }
                if (! $newChanged) {
                    throw Correction::nothingChanged();
                }

                $suratJalan->fill($values + ['total_pallets' => $pallets->count()]);
                if ($file) {
                    $suratJalan->fill(['file_path_surat_jalan' => $path, 'file_name' => $file->getClientOriginalName(), 'file_mime' => $file->getMimeType()]);
                }
                Correction::mark($suratJalan, $request, $reason);
                $suratJalan->save();

                $removed = array_values(array_diff($currentIds, $pallets->pluck('id')->all()));
                $suratJalan->pallets()->sync($allocation);
                RecordSuratJalan::refreshStatuses(B3Pallet::whereIn('id', array_merge($removed, array_keys($allocation)))->lockForUpdate()->get());

                Correction::audit($request, 'correct', $suratJalan, $values['surat_jalan_number'], $reason, $oldChanged, $newChanged);

                return "Surat jalan {$values['surat_jalan_number']} dikoreksi."
                    . ($removed ? ' ' . count($removed) . ' pallet kembali ke TPS.' : '');
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(RecordSuratJalan::DISK)->delete($path);
            }
            throw $e;
        }
    }

    public function delete(Request $request, SuratJalan $suratJalan, string $reason): string
    {
        return DB::transaction(function () use ($request, $suratJalan, $reason) {
            \App\Models\Machine::orderBy('id')->lockForUpdate()->firstOrFail();
            $suratJalan = SuratJalan::with('pallets')->lockForUpdate()->findOrFail($suratJalan->id);
            $ids = $suratJalan->pallets->pluck('id');
            $snapshot = $this->snapshot($suratJalan, $suratJalan->pallets) + ['file_path' => $suratJalan->file_path_surat_jalan];

            $suratJalan->pallets()->detach();
            RecordSuratJalan::refreshStatuses(B3Pallet::whereIn('id', $ids)->lockForUpdate()->get());
            $suratJalan->forceDelete();

            Correction::audit($request, 'delete', $suratJalan, $suratJalan->surat_jalan_number, $reason, $snapshot);

            return "Surat jalan {$suratJalan->surat_jalan_number} dihapus. {$ids->count()} pallet kembali ke TPS.";
        });
    }

    private function ensureAvailable($pallets, array $data, array $currentIds): void
    {
        if ($pallets->count() !== count($data['pallet_ids'])) {
            throw ValidationException::withMessages(['pallet_ids' => 'Ada pallet yang tidak ditemukan. Muat ulang halaman, lalu pilih ulang pallet.']);
        }

        // Boleh: pallet yang sudah ada di surat jalan ini, atau pallet yang sedang di TPS
        $unavailable = $pallets->filter(fn (B3Pallet $p) => ! in_array($p->id, $currentIds) && $p->location() !== 'tps');
        if ($unavailable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pallet_ids' => 'Pallet ' . $unavailable->pluck('pallet_number')->implode(', ') . ' tidak ada di TPS atau sudah diangkut dengan surat jalan lain.',
            ]);
        }

        $latest = $pallets->max(fn (B3Pallet $p) => $p->sent_to_tps_at?->toDateString());
        if ($latest && $data['handover_date'] < $latest) {
            throw ValidationException::withMessages([
                'handover_date' => 'Tanggal serah terima tidak boleh sebelum pallet sampai di TPS (' . Correction::date(Carbon::parse($latest)) . ').',
            ]);
        }
    }

    /** Isi surat jalan dalam bentuk yang terbaca di riwayat koreksi (vendor dan pallet berupa nama). */
    private function snapshot(SuratJalan $suratJalan, $pallets): array
    {
        return [
            'surat_jalan_number' => $suratJalan->surat_jalan_number,
            'vendor' => Vendor::withTrashed()->whereKey($suratJalan->vendor_id)->value('name'),
            'handover_date' => Carbon::parse($suratJalan->handover_date)->toDateString(),
            'weight_kg' => (float) $suratJalan->weight_kg,
            'vendor_officer_name' => $suratJalan->vendor_officer_name,
            'notes' => $suratJalan->notes,
            'pallets' => $pallets->pluck('pallet_number')->sort()->implode(', '),
            'file_name' => $suratJalan->file_name,
        ];
    }
}
