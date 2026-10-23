<?php

namespace App\Support;

use App\Models\B3Pallet;
use App\Models\Parameter;
use App\Models\SuratJalan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Limbah B3 (HSE) untuk satu periode. Penyerahan dihitung dari tanggal serah
 * terima surat jalan; berat resmi dari timbangan vendor per surat jalan. Stok dihitung
 * per tanggal akhir periode (atau hari ini bila periode belum selesai).
 */
final class B3Report
{
    public readonly Carbon $start;

    public readonly Carbon $end;

    public function __construct(public readonly string $period, Carbon $anchor)
    {
        [$this->start, $this->end] = ReportPeriod::range($period, $anchor);
    }

    public function toArray(): array
    {
        $suratJalan = SuratJalan::with(['vendor', 'pallets.drums', 'creator'])
            ->whereDate('handover_date', '>=', $this->start->toDateString())
            ->whereDate('handover_date', '<=', $this->end->toDateString())
            ->orderBy('handover_date')->orderBy('id')
            ->get();
        $compliance = $this->compliance($suratJalan);
        $stock = $this->stock();
        $unit = ReportPeriod::BREAKDOWN[$this->period];

        return [
            'period' => $this->period,
            'period_label' => ReportPeriod::LABELS[$this->period],
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'max_storage_days' => (int) Parameter::number('b3_max_storage_days'),
            'summary' => self::totals($suratJalan) + [
                'over_limit_count' => collect($compliance)->where('over_days', '>', 0)->count(),
                'longest_days_stored' => collect($compliance)->max('days_stored'),
            ],
            'by_vendor' => $suratJalan->groupBy(fn (SuratJalan $s) => $s->vendor?->name ?? '–')
                ->map(fn (Collection $group, string $vendor) => ['vendor' => $vendor] + self::totals($group))
                ->sortByDesc('weight_kg')->values()->all(),
            'breakdown' => $unit ? [
                'unit' => $unit,
                'rows' => array_map(fn (array $b) => ['start' => $b['start'], 'end' => $b['end']]
                    + self::totals($suratJalan->filter(fn (SuratJalan $s) => $s->handover_date->between($b['from'], $b['to']))),
                    ReportPeriod::buckets($this->start, $this->end, $unit)),
            ] : null,
            'surat_jalan' => $suratJalan->map(fn (SuratJalan $s) => $s->present())->all(),
            'compliance' => $compliance,
            'stock' => $stock,
            // Maintenance menghentikan pengiriman residu ke TPS (batas kirim tetap berjalan)
            'maintenance' => MaintenanceHistory::forPeriod($this->start, $this->end),
        ];
    }

    /** @param  Collection<int, SuratJalan>  $suratJalan */
    private static function totals(Collection $suratJalan): array
    {
        return [
            'surat_jalan_count' => $suratJalan->count(),
            'pallet_count' => $suratJalan->sum(fn (SuratJalan $s) => $s->pallets->count()),
            'drum_count' => $suratJalan->sum(fn (SuratJalan $s) => $s->pallets->sum('drum_count')),
            'weight_kg' => round($suratJalan->sum('weight_kg'), 2),
        ];
    }

    /**
     * Lama simpan tiap pallet yang diserahkan di periode ini: dari mulai terisi sampai
     * serah terima, dibandingkan batas masa simpan (FR-26).
     */
    private function compliance(Collection $suratJalan): array
    {
        return $suratJalan->flatMap(fn (SuratJalan $s) => $s->pallets->map(function (B3Pallet $p) use ($s) {
            $days = (int) round($p->start_date->copy()->startOfDay()->diffInDays($s->handover_date->copy()->startOfDay()));

            return [
                'pallet_number' => $p->pallet_number,
                'surat_jalan_number' => $s->surat_jalan_number,
                'vendor' => $s->vendor?->name,
                'start_date' => $p->start_date->toDateString(),
                'sent_to_tps_at' => $p->sent_to_tps_at?->toDateString(),
                'handover_date' => $s->handover_date->toDateString(),
                'expiry_date' => $p->expiry_date->toDateString(),
                'days_stored' => $days,
                'max_days' => $p->max_storage_days,
                'over_days' => max(0, (int) round($p->expiry_date->copy()->startOfDay()->diffInDays($s->handover_date->copy()->startOfDay(), false))),
            ];
        }))->sortBy([['over_days', 'desc'], ['days_stored', 'desc']])->values()->all();
    }

    /** Pallet yang masih tersimpan pada akhir periode, dengan sisa masa simpan saat itu. */
    private function stock(): array
    {
        $at = ($this->end->isFuture() ? now() : $this->end)->copy()->startOfDay();
        $thresholds = StorageStatus::thresholds();

        $pallets = B3Pallet::with(['drums', 'suratJalan'])
            ->whereDate('start_date', '<=', $at->toDateString())
            ->orderBy('expiry_date')->orderBy('id')
            ->get()
            ->filter(function (B3Pallet $p) use ($at) {
                $handover = $p->suratJalan->first()?->handover_date;

                return $handover === null || $handover->copy()->startOfDay()->gt($at);
            });

        return [
            'as_of' => $at->toDateString(),
            'is_current' => $this->end->isFuture(),
            'rows' => $pallets->map(function (B3Pallet $p) use ($at, $thresholds) {
                $days = (int) round($at->diffInDays($p->expiry_date->copy()->startOfDay(), false));
                $atTps = $p->sent_to_tps_at !== null && $p->sent_to_tps_at->copy()->startOfDay()->lte($at);
                // Pallet penuh berisi drum_count drum. Yang masih diisi tidak dihitung per drum
                // lagi (null); hanya data lama yang punya catatan drum.
                $drums = $atTps ? $p->drum_count : $p->drums->filter(fn ($d) => $d->filled_date->copy()->startOfDay()->lte($at))->count();

                return [
                    'pallet_number' => $p->pallet_number,
                    'location' => $atTps ? 'tps' : 'reclaimer',
                    'drum_count' => $drums ?: null,
                    'start_date' => $p->start_date->toDateString(),
                    'sent_to_tps_at' => $atTps ? $p->sent_to_tps_at->toDateString() : null,
                    'expiry_date' => $p->expiry_date->toDateString(),
                    'days_remaining' => $days,
                    'level' => StorageStatus::levelFor($days, $thresholds),
                ];
            })->values()->all(),
        ];
    }

    public function filename(string $extension): string
    {
        return ReportPeriod::filename('Laporan-Limbah-B3', $this->period, $this->start, $this->end, $extension);
    }
}
