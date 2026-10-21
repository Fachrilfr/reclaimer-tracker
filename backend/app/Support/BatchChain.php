<?php

namespace App\Support;

use App\Models\ReclaimerBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu rangkaian proses reclaimer: batch awal ditambah semua batch lanjutannya.
 *
 * Proses yang belum selesai saat shift berganti dilanjutkan shift berikutnya, jadi
 * recovery rate hanya bermakna untuk rangkaian yang sudah selesai. Agregat (dashboard,
 * laporan, per shift) dihitung per rangkaian selesai, satu kali, pada tanggal dan
 * shift batch awalnya.
 */
final class BatchChain
{
    /** @param  Collection<int, ReclaimerBatch>  $batches  batch awal lalu lanjutannya, urut id */
    private function __construct(
        public readonly ReclaimerBatch $root,
        public readonly Collection $batches,
    ) {}

    public static function of(ReclaimerBatch $root): self
    {
        $root->loadMissing('continuations');

        return new self($root, collect([$root])->concat($root->continuations));
    }

    /**
     * Rangkaian yang batch awalnya memenuhi query.
     *
     * @return Collection<int, self>
     */
    public static function whereRoot(callable $scope): Collection
    {
        $query = ReclaimerBatch::query()->whereNull('parent_batch_id')->with(['shift', 'operator', 'reviewer', 'continuations.shift']);
        $scope($query);

        return $query->get()->map(fn (ReclaimerBatch $root) => self::of($root));
    }

    /** @return Collection<int, self> */
    public static function open(): Collection
    {
        return self::whereRoot(fn (Builder $q) => $q->whereNull('chain_completed_at')->orderBy('date')->orderBy('id'));
    }

    public function isComplete(): bool
    {
        return $this->root->chain_completed_at !== null;
    }

    public function last(): ReclaimerBatch
    {
        return $this->batches->last();
    }

    public function input(): float
    {
        return round($this->batches->sum('total_input_weight'), 2);
    }

    public function ink(): float
    {
        return round($this->batches->sum('ink_weight'), 2);
    }

    public function solventUsed(): float
    {
        return round($this->batches->sum('solvent_used_weight'), 2);
    }

    public function recovery(): float
    {
        return round($this->batches->sum('recovery_weight'), 2);
    }

    public function residue(): float
    {
        return round($this->input() - $this->recovery(), 2);
    }

    public function rate(): float
    {
        return MaterialBalance::rate($this->recovery(), $this->input());
    }

    public function isBelowTarget(): bool
    {
        return $this->isComplete() && $this->root->is_recovery_low;
    }

    /**
     * Jumlah satu kumpulan rangkaian. Persentase dari total, bukan rata-rata (PRD §8).
     *
     * @param  Collection<int, self>  $chains
     */
    public static function summarize(Collection $chains): array
    {
        $input = round($chains->sum(fn (self $c) => $c->input()), 2);
        $recovery = round($chains->sum(fn (self $c) => $c->recovery()), 2);
        $residue = round($input - $recovery, 2);

        return [
            'chain_count' => $chains->count(),
            'batch_count' => $chains->sum(fn (self $c) => $c->batches->count()),
            'below_target_count' => $chains->filter(fn (self $c) => $c->isBelowTarget())->count(),
            'input' => $input,
            'ink' => round($chains->sum(fn (self $c) => $c->ink()), 2),
            'solvent_used' => round($chains->sum(fn (self $c) => $c->solventUsed()), 2),
            'recovery' => $recovery,
            'residue' => $residue,
            'recovery_rate' => MaterialBalance::rate($recovery, $input),
            'residue_rate' => MaterialBalance::rate($residue, $input),
        ];
    }

    /** Kelompokkan rangkaian selesai per hari, minggu (Senin s/d Minggu), atau bulan, sampai hari ini. */
    public static function buckets(Collection $completed, Carbon $from, Carbon $to, string $unit): array
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

            $inBucket = $completed->filter(fn (self $c) => $c->root->date->between($cursor, $bucketEnd));
            $buckets[] = ['start' => $cursor->toDateString(), 'end' => $bucketEnd->toDateString()]
                + self::summarize($inBucket);

            $cursor = $bucketEnd->copy()->addDay()->startOfDay();
        }

        return $buckets;
    }

    public function toArray(): array
    {
        $complete = $this->isComplete();

        return [
            'root_id' => $this->root->id,
            'batch_number' => $this->root->batch_number,
            'date' => $this->root->date->toDateString(),
            'shift' => $this->root->shift?->name,
            'operator' => $this->root->operator?->name,
            'batch_numbers' => $this->batches->pluck('batch_number')->all(),
            'batch_count' => $this->batches->count(),
            'last_batch_number' => $this->last()->batch_number,
            'last_date' => $this->last()->date->toDateString(),
            'last_shift' => $this->last()->shift?->name,
            'is_complete' => $complete,
            'completed_at' => $this->root->chain_completed_at?->toDateTimeString(),
            'input' => $this->input(),
            'recovery' => $this->recovery(),
            'residue' => $this->residue(),
            // Recovery rate hanya ditampilkan setelah rangkaian selesai
            'recovery_rate' => $complete ? $this->root->chain_recovery_rate : null,
            'target_recovery_rate' => $complete ? $this->root->target_recovery_rate : null,
            'is_below_target' => $this->isBelowTarget(),
            'review' => $this->root->reviewSummary(),
        ];
    }
}
