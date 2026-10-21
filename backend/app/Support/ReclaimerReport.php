<?php

namespace App\Support;

use App\Models\Parameter;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Reclaimer (FR-55) untuk satu periode: harian, mingguan, bulanan, tahunan.
 *
 * Angka dihitung dengan BatchChain yang sama dengan dashboard, jadi laporan dan
 * dashboard selalu sama (kriteria penerimaan PRD §13 no. 6): per rangkaian selesai,
 * pada tanggal dan shift batch awalnya, persentase dari total.
 */
final class ReclaimerReport
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    public const PERIOD_LABELS = ['day' => 'Harian', 'week' => 'Mingguan', 'month' => 'Bulanan', 'year' => 'Tahunan'];

    public readonly Carbon $start;

    public readonly Carbon $end;

    public function __construct(public readonly string $period, Carbon $anchor)
    {
        [$this->start, $this->end] = match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
        };
    }

    public function toArray(): array
    {
        $chains = BatchChain::whereRoot(fn (Builder $q) => $q
            ->whereDate('date', '>=', $this->start->toDateString())
            ->whereDate('date', '<=', $this->end->toDateString())
            ->orderBy('date')->orderBy('id'));
        $completed = $chains->filter(fn (BatchChain $c) => $c->isComplete())->values();

        // Rincian sesuai FR-55: harian per rangkaian, mingguan per hari, bulanan per minggu, tahunan per bulan
        $breakdownUnit = ['day' => null, 'week' => 'day', 'month' => 'week', 'year' => 'month'][$this->period];

        return [
            'period' => $this->period,
            'period_label' => self::PERIOD_LABELS[$this->period],
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'target_recovery_rate' => Parameter::number('target_recovery_rate'),
            'summary' => BatchChain::summarize($completed) + [
                'open_chain_count' => $chains->count() - $completed->count(),
            ],
            'solvent' => $this->solvent(),
            'by_shift' => Shift::where('is_active', true)->orderBy('id')->get()
                ->map(fn (Shift $shift) => ['shift' => $shift->name]
                    + BatchChain::summarize($completed->filter(fn (BatchChain $c) => $c->root->shift_id === $shift->id)))
                ->all(),
            'breakdown' => $breakdownUnit
                ? ['unit' => $breakdownUnit, 'rows' => BatchChain::buckets($completed, $this->start, $this->end, $breakdownUnit)]
                : null,
            'chains' => $chains->map(fn (BatchChain $c) => $this->chainRow($c))->all(),
            'below_target' => $completed->filter(fn (BatchChain $c) => $c->isBelowTarget())
                ->map(fn (BatchChain $c) => $this->chainRow($c))->values()->all(),
            'temp_abnormal' => $this->abnormalBatches(),
            'maintenance' => $this->maintenance(),
        ];
    }

    private function chainRow(BatchChain $chain): array
    {
        return $chain->toArray() + [
            'regu' => $chain->root->regu,
            'ink' => $chain->ink(),
            'solvent_used' => $chain->solventUsed(),
        ];
    }

    /** Saldo awal, mutasi, dan saldo akhir solvent di periode ini (FR-11). */
    private function solvent(): array
    {
        $mutations = SolventLedger::whereBetween('mutation_date', [$this->start, $this->end])->get();
        $closingAt = $this->end->isFuture() ? now() : $this->end;

        return [
            'opening' => SolventLedger::balanceAt($this->start->copy()->subSecond()),
            'used' => round($mutations->where('type', 'out')->sum('amount'), 2),
            'recovered' => round($mutations->where('type', 'in')->sum('amount'), 2),
            'adjustment' => round($mutations->where('type', 'adjustment')
                ->sum(fn ($m) => $m->current_balance - $m->previous_balance), 2),
            'closing' => SolventLedger::balanceAt($closingAt),
            'closing_is_current' => $this->end->isFuture(),
        ];
    }

    private function abnormalBatches(): array
    {
        return ReclaimerBatch::with('shift')
            ->where('is_temp_abnormal', true)
            ->whereDate('date', '>=', $this->start->toDateString())
            ->whereDate('date', '<=', $this->end->toDateString())
            ->orderBy('date')->orderBy('id')
            ->get()
            ->map(fn (ReclaimerBatch $b) => [
                'batch_number' => $b->batch_number,
                'date' => $b->date->toDateString(),
                'shift' => $b->shift?->name,
                'oil_temp' => $b->oil_temp,
                'vapour_temp' => $b->vapour_temp,
            ])
            ->all();
    }

    /** Downtime maintenance yang beririsan dengan periode, durasinya dipotong ke periode. */
    private function maintenance(): array
    {
        $windows = MaintenanceHistory::windows()
            ->filter(fn (array $w) => $w['start']->lte($this->end) && ($w['end'] === null || $w['end']->gte($this->start)))
            ->values();

        $rows = $windows->map(function (array $w) {
            $from = $w['start']->max($this->start);
            $to = ($w['end'] ?? now())->min($this->end)->min(now());

            return MaintenanceHistory::present($w) + [
                'minutes_in_period' => max(0, (int) round($from->diffInMinutes($to))),
            ];
        });

        return [
            'count' => $rows->count(),
            'total_minutes' => (int) $rows->sum('minutes_in_period'),
            'rows' => $rows->all(),
        ];
    }

    /** Nama berkas ekspor, contoh Laporan-Reclaimer-Bulanan-2026-10. */
    public function filename(string $extension): string
    {
        $suffix = match ($this->period) {
            'day' => $this->start->format('Y-m-d'),
            'week' => $this->start->format('Y-m-d') . '_' . $this->end->format('Y-m-d'),
            'month' => $this->start->format('Y-m'),
            'year' => $this->start->format('Y'),
        };

        return 'Laporan-Reclaimer-' . self::PERIOD_LABELS[$this->period] . "-{$suffix}.{$extension}";
    }
}
