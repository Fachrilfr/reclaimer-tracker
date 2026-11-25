<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\B3Pallet;
use App\Models\SuratJalan;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Serah terima pallet dari TPS ke vendor. Surat jalan adalah penutup dokumen:
 * pallet langsung berstatus diserahkan dan pengingat masa simpannya berhenti.
 */
class RecordSuratJalan
{
    public const DRUMS_PER_DELIVERY = 20;

    public const DISK = 'local';

    /** Bukti surat jalan opsional (meeting 30 Okt 2026). */
    public function handle(Request $request, array $data, ?UploadedFile $file): SuratJalan
    {
        $path = $file?->storeAs('surat-jalan', Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()), self::DISK);

        try {
            return DB::transaction(function () use ($request, $data, $file, $path) {
                \App\Models\Machine::orderBy('id')->lockForUpdate()->firstOrFail();
                $pallets = B3Pallet::whereIn('id', $data['pallet_ids'])->lockForUpdate()->get();
                $this->ensureAvailable($pallets, $data);

                $allocation = self::allocate($pallets, $data);

                $suratJalan = SuratJalan::create([
                    'surat_jalan_number' => trim($data['surat_jalan_number']),
                    'vendor_id' => $data['vendor_id'],
                    'handover_date' => $data['handover_date'],
                    'total_pallets' => $pallets->count(),
                    'weight_kg' => round((float) $data['weight_kg'], 2),
                    'vendor_officer_name' => trim($data['vendor_officer_name']),
                    'file_path_surat_jalan' => $path,
                    'file_name' => $file?->getClientOriginalName(),
                    'file_mime' => $file?->getMimeType(),
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                    'created_by' => $request->user()->id,
                ]);
                $suratJalan->pallets()->attach($allocation);
                self::refreshStatuses($pallets);

                AuditLog::record($request, 'create', $suratJalan, $suratJalan->only([
                    'surat_jalan_number', 'vendor_id', 'handover_date', 'total_pallets', 'weight_kg', 'vendor_officer_name', 'file_name',
                ]) + ['pallets' => $pallets->pluck('pallet_number')->all()]);

                return $suratJalan;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }
    }

    public static function allocate($pallets, array $data, ?SuratJalan $current = null): array
    {
        $allocation = [];
        if (isset($data['quantities']) && (array_diff(array_keys($data['quantities']), $data['pallet_ids'])
            || array_diff($data['pallet_ids'], array_keys($data['quantities'])))) {
            throw ValidationException::withMessages(['pallet_ids' => 'Tentukan jumlah drum untuk setiap pallet yang dipilih.']);
        }
        foreach ($pallets as $pallet) {
            $available = $pallet->remainingDrums(exceptShipment: $current?->id);
            $old = $current?->pallets->firstWhere('id', $pallet->id)?->pivot?->drum_count;
            $count = (int) ($data['quantities'][$pallet->id] ?? $old ?? $available);
            if ($count < 1 || $count > $available) {
                throw ValidationException::withMessages(['pallet_ids' => "Jumlah drum dari {$pallet->pallet_number} melebihi stok yang tersedia."]);
            }
            $allocation[$pallet->id] = ['drum_count' => $count];
        }
        $total = array_sum(array_column($allocation, 'drum_count'));
        // Dokumen lama tetap dapat dikoreksi tanpa mengganti jumlah drum historisnya.
        $unchanged = $current && $current->pallets->count() === count($allocation)
            && $current->pallets->every(fn ($p) => ($allocation[$p->id]['drum_count'] ?? 0) === (int) ($p->pivot->drum_count ?? $p->drum_count));
        if ($total !== self::DRUMS_PER_DELIVERY && ! $unchanged) {
            throw ValidationException::withMessages(['pallet_ids' => "Setiap pengangkutan harus tepat 20 drum. Terpilih {$total} drum."]);
        }
        return $allocation;
    }

    public static function refreshStatuses($pallets): void
    {
        $pallets->load('suratJalan');
        foreach ($pallets as $pallet) {
            $pallet->update(['status' => $pallet->remainingDrums() > 0 ? 'full' : 'transported']);
        }
    }

    private function ensureAvailable($pallets, array $data): void
    {
        if ($pallets->count() !== count($data['pallet_ids'])) {
            throw ValidationException::withMessages(['pallet_ids' => 'Ada pallet yang tidak ditemukan. Muat ulang halaman, lalu pilih ulang pallet.']);
        }

        $unavailable = $pallets->filter(fn (B3Pallet $p) => $p->location() !== 'tps');
        if ($unavailable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pallet_ids' => 'Pallet ' . $unavailable->pluck('pallet_number')->implode(', ') . ' tidak ada di TPS atau sudah diserahkan. Pilih pallet yang tersedia di TPS.',
            ]);
        }

        // Pallet tidak bisa diserahkan sebelum sampai di TPS
        $latest = $pallets->max(fn (B3Pallet $p) => $p->sent_to_tps_at->toDateString());
        if ($data['handover_date'] < $latest) {
            throw ValidationException::withMessages([
                'handover_date' => 'Tanggal serah terima tidak boleh sebelum pallet sampai di TPS (' . Carbon::parse($latest)->locale('id')->translatedFormat('j M Y') . ').',
            ]);
        }
    }
}
