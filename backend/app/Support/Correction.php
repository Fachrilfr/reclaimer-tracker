<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Material;
use App\Models\SolventLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bersama fitur Koreksi data (Supervisor dan Super Admin):
 * - alasan wajib untuk setiap koreksi dan hapus;
 * - nilai lama dan baru dicatat di audit_logs, hanya kolom yang berubah;
 * - baris yang dikoreksi diberi tanda (lihat HasCorrection);
 * - perubahan saldo solvent dicatat sebagai baris penyesuaian baru, bukan mengubah
 *   mutasi lama, supaya saldo sesudahnya tetap urut dan konsisten.
 */
final class Correction
{
    /** Entitas yang bisa dikoreksi, sama dengan entity_type di audit_logs. */
    public const ENTITIES = [
        'reclaimer_batches' => 'Input reclaimer',
        'solvent_usages' => 'Pemakaian solvent',
        'b3_pallets' => 'Pallet residu',
        'surat_jalan' => 'Surat jalan',
        'machine_maintenance_logs' => 'Maintenance mesin',
    ];

    /** Alasan hapus. Untuk koreksi, aturan alasan digabung dengan isian lain (reasonRules). */
    public static function reason(Request $request, bool $deleting = false): string
    {
        return trim($request->validate(self::reasonRules(), self::reasonMessages($deleting))['reason']);
    }

    public static function reasonRules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public static function reasonMessages(bool $deleting = false): array
    {
        return [
            'reason.required' => $deleting
                ? 'Tulis alasan menghapus data ini, misalnya "Input tercatat dua kali".'
                : 'Tulis alasan koreksi, misalnya "Salah ketik hasil solvent, seharusnya 150 kg".',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan paling panjang 500 karakter.',
        ];
    }

    /** Filter daftar di halaman Koreksi data: cari, rentang tanggal, halaman. */
    public static function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'from.date_format' => 'Tanggal awal tidak valid.',
            'to.date_format' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'q.max' => 'Kata kunci paling panjang 100 karakter.',
        ]);
    }

    /** Info halaman, sama untuk semua daftar koreksi. */
    public static function pageMeta(LengthAwarePaginator $page): array
    {
        return [
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'today' => now()->toDateString(),
        ];
    }

    public static function like(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)) . '%';
    }

    /**
     * Kolom yang berubah saja, sebagai [nilai lama, nilai baru]. Angka dibandingkan
     * sebagai angka (150 sama dengan 150.0), teks kosong sama dengan null.
     *
     * @return array{0: array, 1: array}
     */
    public static function diff(array $old, array $new): array
    {
        $before = [];
        $after = [];
        foreach ($new as $key => $value) {
            $previous = $old[$key] ?? null;
            if (self::normalize($previous) === self::normalize($value)) {
                continue;
            }
            $before[$key] = $previous;
            $after[$key] = $value;
        }

        return [$before, $after];
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return is_numeric($value) ? round((float) $value, 4) : $value;
    }

    public static function nothingChanged(): ValidationException
    {
        return ValidationException::withMessages(['reason' => 'Tidak ada data yang diubah. Ubah isian yang salah dulu, lalu simpan.']);
    }

    /** Tandai baris sebagai dikoreksi. Disimpan bersama perubahan lain oleh pemanggil. */
    public static function mark(Model $model, Request $request, string $reason): void
    {
        $model->forceFill([
            'corrected_at' => now(),
            'corrected_by' => $request->user()->id,
            'correction_reason' => $reason,
        ]);
    }

    /**
     * Jejak audit koreksi ("correct") atau hapus ("delete"). Label (nomor input, nomor
     * pallet, dan sebagainya) ikut disimpan supaya riwayat tetap terbaca setelah data dihapus.
     */
    public static function audit(Request $request, string $action, Model $entity, string $label, string $reason, array $old, array $new = []): void
    {
        AuditLog::record($request, $action, $entity, array_merge($new, ['label' => $label, 'reason' => $reason]), $old);
    }

    /** Kunci saldo yang sama dengan RecordBatch dan RecordSolventUsage. */
    public static function lockSolvent(): void
    {
        Material::where('code', 'SOLVENT')->lockForUpdate()->firstOrFail();
    }

    /**
     * Baris penyesuaian di buku besar solvent. Pemanggil sudah memegang kunci saldo.
     *
     * @param  array{batch_id?: int, solvent_usage_id?: int}  $link  data asal penyesuaian
     * @return float saldo setelah penyesuaian
     */
    public static function adjustSolvent(Request $request, float $delta, string $note, array $link = []): float
    {
        $before = SolventLedger::currentBalance(lock: true);
        $after = round($before + $delta, 2);

        SolventLedger::create($link + [
            'mutation_date' => now(),
            'type' => 'adjustment',
            'amount' => round($delta, 2),
            'previous_balance' => $before,
            'current_balance' => $after,
            'reason' => mb_substr($note, 0, 255),
            'user_id' => $request->user()->id,
            'created_at' => now(),
        ]);

        return $after;
    }

    /** Kalimat penyesuaian saldo untuk pesan sukses, dengan peringatan bila saldo jadi minus. */
    public static function solventMessage(float $delta, float $balance): string
    {
        $text = ' Saldo solvent disesuaikan ' . ($delta > 0 ? '+' : '−') . self::kg(abs($delta)) . ' kg, menjadi ' . self::kg($balance) . ' kg.';
        if ($balance < 0) {
            $text .= ' Saldo minus: periksa pemakaian solvent yang tercatat setelah data ini.';
        }

        return $text;
    }

    public static function kg(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function date(Carbon|string|null $date): string
    {
        return $date ? Carbon::parse($date)->locale('id')->translatedFormat('j M Y') : '–';
    }
}
