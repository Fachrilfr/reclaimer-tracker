<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Periode laporan: harian, mingguan (Senin s/d Minggu), bulanan, tahunan.
 * Dipakai Laporan Reclaimer dan Laporan Limbah B3 supaya rentang dan nama berkasnya sama.
 */
final class ReportPeriod
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    public const LABELS = ['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan', 'year' => 'Tahunan'];

    /** Rincian per periode: mingguan per hari, bulanan per minggu, tahunan per bulan. */
    public const BREAKDOWN = ['day' => null, 'week' => 'day', 'month' => 'week', 'year' => 'month'];

    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
        };
    }

    /** Contoh: Laporan-Reclaimer-Bulanan-2026-10.xlsx */
    public static function filename(string $prefix, string $period, Carbon $start, Carbon $end, string $extension): string
    {
        $suffix = match ($period) {
            'day' => $start->format('Y-m-d'),
            'week' => $start->format('Y-m-d') . '_' . $end->format('Y-m-d'),
            'month' => $start->format('Y-m'),
            'year' => $start->format('Y'),
        };

        return "{$prefix}-" . self::LABELS[$period] . "-{$suffix}.{$extension}";
    }

    /**
     * Ember waktu untuk rincian, dari awal periode sampai hari ini (atau akhir periode).
     *
     * @return list<array{start: string, end: string, from: Carbon, to: Carbon}>
     */
    public static function buckets(Carbon $from, Carbon $to, string $unit): array
    {
        $buckets = [];
        $cursor = $from->copy()->startOfDay();
        $last = $to->isFuture() ? now()->endOfDay() : $to;

        while ($cursor->lte($last)) {
            $bucketEnd = match ($unit) {
                'day' => $cursor->copy()->endOfDay(),
                'week' => $cursor->copy()->endOfWeek(Carbon::SUNDAY)->min($to),
                'month' => $cursor->copy()->endOfMonth(),
            };
            $buckets[] = ['start' => $cursor->toDateString(), 'end' => $bucketEnd->toDateString(), 'from' => $cursor->copy(), 'to' => $bucketEnd->copy()];
            $cursor = $bucketEnd->copy()->addDay()->startOfDay();
        }

        return $buckets;
    }
}
