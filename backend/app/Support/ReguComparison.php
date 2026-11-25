<?php

namespace App\Support;

use App\Models\ReclaimerBatch;
use App\Models\SolventUsage;
use Illuminate\Support\Carbon;

final class ReguComparison
{
    public static function between(Carbon $from, Carbon $to): array
    {
        $dates = [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()];
        $empty = ['recovery' => 0, 'residue' => 0, 'used' => 0];
        $rows = array_fill_keys(['A', 'B', 'C'], $empty);
        $add = function (?string $regu, string $metric, float $kg) use (&$rows, $empty) {
            $regu = trim((string) $regu) ?: 'Belum tercatat';
            $rows[$regu] ??= $empty;
            $rows[$regu][$metric] = round($rows[$regu][$metric] + $kg, 2);
        };

        foreach (ReclaimerBatch::whereBetween('date', $dates)->groupBy('regu')
            ->selectRaw('regu, SUM(recovery_weight) AS kg')->get() as $row) {
            $add($row->regu, 'recovery', $row->kg);
        }
        foreach (SolventUsage::whereBetween('date', $dates)->groupBy('regu')
            ->selectRaw('regu, SUM(weight) AS kg')->get() as $row) {
            $add($row->regu, 'used', $row->kg);
        }
        $closed = ReclaimerBatch::whereBetween('date', $dates)->where('process_status', 'completed')
            ->with(['parent.continuations', 'continuations'])->get();
        foreach ($closed as $batch) {
            $chain = BatchChain::of($batch->root());
            if ($chain->isComplete() && $chain->last()->is($batch)) {
                $add($batch->regu, 'residue', $chain->residue());
            }
        }

        return collect($rows)->map(fn ($values, $regu) => ['regu' => $regu] + $values)->values()->all();
    }
}
