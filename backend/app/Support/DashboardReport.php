<?php

namespace App\Support;

use App\Http\Controllers\Api\PalletController;
use App\Models\B3Pallet;
use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi dashboard (FR-45 s/d FR-50) untuk satu periode: harian, bulanan, atau tahunan.
 * Semua persentase dihitung dari jumlah total, bukan rata-rata persentase batch (PRD §8).
 */
final class DashboardReport
{
    private readonly Carbon $start;

    private readonly Carbon $end;

    public function __construct(private readonly string $period, Carbon $anchor)
    {
        [$this->start, $this->end] = match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
        };
    }

    public function toArray(): array
    {
        $batches = ReclaimerBatch::with(['shift', 'operator', 'parent'])
            ->whereDate('date', '>=', $this->start->toDateString())
            ->whereDate('date', '<=', $this->end->toDateString())
            ->orderBy('date')->orderBy('id')
            ->get();

        $thresholds = StorageStatus::thresholds();
        $activePallets = B3Pallet::where('status', '!=', 'transported')->orderBy('expiry_date')->get()
            ->map(fn (B3Pallet $pallet) => PalletController::present($pallet, $thresholds));

        // Saldo pada akhir periode; untuk periode yang masih berjalan, saldo saat ini
        $balanceAt = $this->end->isFuture() ? now() : $this->end;

        return [
            'period' => $this->period,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'target_recovery_rate' => Parameter::number('target_recovery_rate'),
            'totals' => self::summarize($batches) + ['solvent_balance' => SolventLedger::balanceAt($balanceAt)],
            'by_shift' => $this->byShift($batches),
            'breakdown' => $this->breakdown($batches),
            'action_items' => [
                'pallets' => $activePallets->filter(fn ($pallet) => $pallet['storage']['needs_action'])->values(),
                'low_recovery_batches' => $this->lowRecoveryBatches(),
            ],
            'active_pallets' => $activePallets,
        ];
    }

    private static function summarize(Collection $batches): array
    {
        $input = round($batches->sum('total_input_weight'), 2);
        $recovery = round($batches->sum('recovery_weight'), 2);
        $residue = round($batches->sum('residue_estimated_weight'), 2);

        return [
            'batch_count' => $batches->count(),
            'input' => $input,
            'ink' => round($batches->sum('ink_weight'), 2),
            'solvent_used' => round($batches->sum('solvent_used_weight'), 2),
            'recovery' => $recovery,
            'residue' => $residue,
            'recovery_rate' => MaterialBalance::rate($recovery, $input),
            'residue_rate' => MaterialBalance::rate($residue, $input),
        ];
    }

    private function byShift(Collection $batches): array
    {
        return Shift::where('is_active', true)->orderBy('id')->get()
            ->map(fn (Shift $shift) => ['shift' => $shift->name]
                + self::summarize($batches->where('shift_id', $shift->id)))
            ->all();
    }

    /**
     * FR-49: harian per batch, bulanan per minggu, tahunan per bulan.
     */
    private function breakdown(Collection $batches): array
    {
        if ($this->period === 'day') {
            return [
                'unit' => 'batch',
                'rows' => $batches->map(fn ($batch) => [
                    'batch_number' => $batch->batch_number,
                    'shift' => $batch->shift?->name,
                    'operator' => $batch->operator?->name,
                    'is_continuation' => $batch->is_continuation,
                    'parent_batch_number' => $batch->parent?->batch_number,
                    'input' => $batch->total_input_weight,
                    'recovery' => $batch->recovery_weight,
                    'residue' => $batch->residue_estimated_weight,
                    'recovery_rate' => $batch->recovery_rate_percent,
                    'solvent_balance_after' => $batch->solvent_balance_after,
                    'is_recovery_low' => $batch->is_recovery_low,
                    'is_temp_abnormal' => $batch->is_temp_abnormal,
                ])->values()->all(),
            ];
        }

        $buckets = [];
        $cursor = $this->start->copy();
        $last = $this->end->isFuture() ? now()->endOfDay() : $this->end;

        while ($cursor->lte($last)) {
            $bucketEnd = $this->period === 'month'
                ? $cursor->copy()->endOfWeek(Carbon::SUNDAY)->min($this->end)
                : $cursor->copy()->endOfMonth();

            $inBucket = $batches->filter(fn ($batch) => $batch->date->between($cursor->copy()->startOfDay(), $bucketEnd));
            $buckets[] = ['start' => $cursor->toDateString(), 'end' => $bucketEnd->toDateString()] + self::summarize($inBucket);

            $cursor = $bucketEnd->copy()->addDay()->startOfDay();
        }

        return ['unit' => $this->period === 'month' ? 'week' : 'month', 'rows' => $buckets];
    }

    /** FR-45: batch dengan recovery di bawah target dalam 14 hari terakhir, terlepas dari periode yang dipilih. */
    private function lowRecoveryBatches(): array
    {
        return ReclaimerBatch::with('shift')
            ->where('is_recovery_low', true)
            ->whereDate('date', '>=', now()->subDays(13)->toDateString())
            ->orderByDesc('date')->orderByDesc('id')
            ->get()
            ->map(fn ($batch) => [
                'batch_number' => $batch->batch_number,
                'date' => $batch->date->toDateString(),
                'shift' => $batch->shift?->name,
                'recovery_rate' => $batch->recovery_rate_percent,
                'supervisor_reviewed' => $batch->supervisor_reviewed,
            ])
            ->all();
    }
}
