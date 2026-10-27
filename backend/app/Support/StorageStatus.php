<?php

namespace App\Support;

use App\Models\B3Pallet;
use App\Models\Parameter;
use Carbon\CarbonInterface;

/**
 * Status masa simpan pallet B3 dari sisa hari eksak (FR-26, FR-28).
 *
 * Jenjang mengikuti parameter pengingat PRD FR-27: sisa <= 30 hari perhatian,
 * <= 14 mendesak, <= 7 kritis (termasuk yang sudah lewat batas).
 *
 * Masa simpan berjalan sejak pallet sampai di TPS. Pallet yang masih di area reclaimer
 * berjenjang `reclaimer`: belum punya sisa hari dan tidak memicu pengingat.
 */
final class StorageStatus
{
    public const NEEDS_ACTION = ['mendesak', 'kritis'];

    private function __construct(
        public readonly string $level,
        public readonly ?int $daysRemaining,
        public readonly ?int $daysElapsed,
        public readonly ?int $maxDays,
    ) {}

    /**
     * Ambang dari tabel parameter. Ambil sekali lalu teruskan saat menghitung banyak pallet.
     *
     * @return array{early: int, urgent: int, critical: int}
     */
    public static function thresholds(): array
    {
        return [
            'early' => (int) Parameter::number('b3_reminder_early_days'),
            'urgent' => (int) Parameter::number('b3_reminder_urgent_days'),
            'critical' => (int) Parameter::number('b3_reminder_critical_days'),
        ];
    }

    public static function forPallet(B3Pallet $pallet, ?CarbonInterface $today = null, ?array $thresholds = null): self
    {
        if ($pallet->status === 'transported') {
            return new self('diserahkan', null, null, $pallet->max_storage_days);
        }
        if ($pallet->sent_to_tps_at === null) {
            return new self('reclaimer', null, null, $pallet->max_storage_days);
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $thresholds ??= self::thresholds();
        $days = (int) round($today->diffInDays($pallet->expiry_date->copy()->startOfDay(), false));

        $level = self::levelFor($days, $thresholds);

        $elapsed = (int) round($pallet->sent_to_tps_at->copy()->startOfDay()->diffInDays($today, false));

        return new self($level, $days, max(0, $elapsed), $pallet->max_storage_days);
    }

    /** Jenjang dari sisa hari, juga untuk posisi stok pada tanggal lampau (laporan). */
    public static function levelFor(int $days, array $thresholds): string
    {
        return match (true) {
            $days <= $thresholds['critical'] => 'kritis',
            $days <= $thresholds['urgent'] => 'mendesak',
            $days <= $thresholds['early'] => 'perhatian',
            default => 'aman',
        };
    }

    public function needsAction(): bool
    {
        return in_array($this->level, self::NEEDS_ACTION, true);
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'days_remaining' => $this->daysRemaining,
            'days_elapsed' => $this->daysElapsed,
            'max_days' => $this->maxDays,
            'needs_action' => $this->needsAction(),
        ];
    }
}
