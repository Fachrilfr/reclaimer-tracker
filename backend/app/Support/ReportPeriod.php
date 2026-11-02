<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Periode laporan: harian, mingguan, bulanan, tahunan. Mingguan memakai tanggal awal dan
 * akhir pilihan pengguna; tanpa tanggal akhir, 7 hari sejak tanggal awal.
 * Dipakai dashboard dan semua laporan supaya rentang dan nama berkasnya sama.
 */
final class ReportPeriod
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    public const LABELS = ['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan', 'year' => 'Tahunan'];

    /** Rincian per periode: mingguan per hari, bulanan per minggu, tahunan per bulan. */
    public const BREAKDOWN = ['day' => null, 'week' => 'day', 'month' => 'week', 'year' => 'month'];

    /** Rentang mingguan paling panjang, supaya rincian per harinya tetap terbaca. */
    public const MAX_WEEK_DAYS = 31;

    /**
     * Periode, tanggal, dan tanggal akhir (khusus mingguan) dari query string.
     *
     * @param  list<string>  $periods
     * @return array{0: string, 1: Carbon, 2: ?Carbon}
     */
    public static function fromRequest(Request $request, string $default, array $periods = self::PERIODS): array
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:' . implode(',', $periods)],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
        ], [
            'period.in' => 'Periode harus ' . self::joinLabels($periods) . '.',
            'date.date_format' => 'Tanggal tidak valid.',
            'date.before_or_equal' => 'Tanggal tidak boleh setelah hari ini.',
            'end.date_format' => 'Tanggal akhir tidak valid.',
            'end.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $period = $data['period'] ?? $default;
        $anchor = isset($data['date']) ? Carbon::parse($data['date']) : now();
        $end = $period === 'week' && isset($data['end']) ? Carbon::parse($data['end']) : null;

        if ($end && $anchor->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) >= self::MAX_WEEK_DAYS) {
            throw ValidationException::withMessages(['end' => 'Rentang mingguan paling panjang ' . self::MAX_WEEK_DAYS . ' hari.']);
        }

        return [$period, $anchor, $end];
    }

    /** @param  list<string>  $periods */
    private static function joinLabels(array $periods): string
    {
        $labels = array_map(fn (string $p) => strtolower(self::LABELS[$p]), $periods);
        $last = array_pop($labels);

        return $labels ? implode(', ', $labels) . ', atau ' . $last : $last;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(string $period, Carbon $anchor, ?Carbon $until = null): array
    {
        return match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfDay(), ($until ?? $anchor->copy()->addDays(6))->copy()->endOfDay()],
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
