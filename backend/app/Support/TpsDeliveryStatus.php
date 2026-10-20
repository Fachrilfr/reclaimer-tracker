<?php

namespace App\Support;

use App\Models\B3Pallet;
use App\Models\Parameter;
use Carbon\CarbonInterface;

/**
 * Status pengiriman pallet dari area reclaimer ke TPS (tugas operator).
 *
 * Selama belum dikirim: aman, perhatian (sisa <= 3 hari), mendesak (sisa <= 1 hari,
 * termasuk hari batas), kritis (sudah lewat batas). Pallet penuh dianggap sudah
 * dikirim ke TPS dan diserahkan ke HSE: status "terkirim".
 */
final class TpsDeliveryStatus
{
    public const NEEDS_ACTION = ['mendesak', 'kritis'];

    private function __construct(
        public readonly string $level,
        public readonly ?int $daysRemaining,
        public readonly ?int $daysElapsed,
        public readonly int $maxDays,
        public readonly ?string $sentAt,
        public readonly int $lateDays,
    ) {}

    /** @return array{early: int, urgent: int} */
    public static function thresholds(): array
    {
        return [
            'early' => (int) Parameter::number('tps_reminder_early_days'),
            'urgent' => (int) Parameter::number('tps_reminder_urgent_days'),
        ];
    }

    public static function forPallet(B3Pallet $pallet, ?CarbonInterface $today = null, ?array $thresholds = null): self
    {
        $due = ($pallet->tps_due_date ?? $pallet->start_date->copy()->addDays((int) Parameter::number('tps_delivery_days')))
            ->copy()->startOfDay();
        $maxDays = (int) round($pallet->start_date->copy()->startOfDay()->diffInDays($due));

        if ($pallet->sent_to_tps_at) {
            $late = (int) round($due->diffInDays($pallet->sent_to_tps_at->copy()->startOfDay(), false));

            return new self('terkirim', null, null, $maxDays, $pallet->sent_to_tps_at->toDateString(), max(0, $late));
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $thresholds ??= self::thresholds();
        $days = (int) round($today->diffInDays($due, false));

        $level = match (true) {
            $days < 0 => 'kritis',
            $days <= $thresholds['urgent'] => 'mendesak',
            $days <= $thresholds['early'] => 'perhatian',
            default => 'aman',
        };
        $elapsed = (int) round($pallet->start_date->copy()->startOfDay()->diffInDays($today, false));

        return new self($level, $days, max(0, $elapsed), $maxDays, null, 0);
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
            'sent_at' => $this->sentAt,
            'late_days' => $this->lateDays,
            'needs_action' => $this->needsAction(),
        ];
    }
}
