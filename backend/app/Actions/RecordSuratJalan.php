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
    public const DISK = 'local';

    public function handle(Request $request, array $data, UploadedFile $file): SuratJalan
    {
        $path = $file->storeAs('surat-jalan', Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()), self::DISK);

        try {
            return DB::transaction(function () use ($request, $data, $file, $path) {
                $pallets = B3Pallet::whereIn('id', $data['pallet_ids'])->lockForUpdate()->get();
                $this->ensureAvailable($pallets, $data);

                $suratJalan = SuratJalan::create([
                    'surat_jalan_number' => trim($data['surat_jalan_number']),
                    'vendor_id' => $data['vendor_id'],
                    'handover_date' => $data['handover_date'],
                    'total_pallets' => $pallets->count(),
                    'weight_kg' => round((float) $data['weight_kg'], 2),
                    'vendor_officer_name' => filled($data['vendor_officer_name'] ?? null) ? trim($data['vendor_officer_name']) : null,
                    'file_path_surat_jalan' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_mime' => $file->getMimeType(),
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                    'created_by' => $request->user()->id,
                ]);
                $suratJalan->pallets()->attach($pallets->pluck('id'));
                B3Pallet::whereIn('id', $pallets->pluck('id'))->update(['status' => 'transported']);

                AuditLog::record($request, 'create', $suratJalan, $suratJalan->only([
                    'surat_jalan_number', 'vendor_id', 'handover_date', 'total_pallets', 'weight_kg', 'vendor_officer_name', 'file_name',
                ]) + ['pallets' => $pallets->pluck('pallet_number')->all()]);

                return $suratJalan;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
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
