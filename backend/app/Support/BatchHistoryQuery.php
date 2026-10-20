<?php

namespace App\Support;

use App\Models\ReclaimerBatch;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Riwayat batch dengan filter, dihitung per rangkaian supaya batch awal dan
 * lanjutannya selalu tampil bersama (recovery gabungannya tidak terpotong).
 *
 * - Tanggal: tanggal batch awal rangkaian, sama seperti dashboard.
 * - Shift, cari, suhu tidak normal: cocok bila salah satu batch di rangkaian cocok.
 * - Di bawah target, belum selesai, sudah ditinjau: status rangkaian.
 */
final class BatchHistoryQuery
{
    public const STATUSES = ['below_target', 'in_progress', 'temp_abnormal', 'reviewed'];

    /** @param  array{from?: ?string, to?: ?string, q?: ?string, status?: ?string, shift_id?: ?int}  $filters */
    public static function roots(array $filters): Builder
    {
        $query = ReclaimerBatch::query()->whereNull('parent_batch_id');

        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        match ($filters['status'] ?? null) {
            'below_target' => $query->whereNotNull('chain_completed_at')->where('is_recovery_low', true),
            'in_progress' => $query->whereNull('chain_completed_at'),
            'reviewed' => $query->where('supervisor_reviewed', true),
            'temp_abnormal' => self::anyMember($query, fn (QueryBuilder $m) => $m->where('m.is_temp_abnormal', true)),
            default => null,
        };

        if (! empty($filters['shift_id'])) {
            self::anyMember($query, fn (QueryBuilder $m) => $m->where('m.shift_id', $filters['shift_id']));
        }

        if (filled($filters['q'] ?? null)) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filters['q'])) . '%';
            self::anyMember($query, fn (QueryBuilder $m) => $m
                ->leftJoin('users as u', 'u.id', '=', 'm.operator_id')
                ->where(fn (QueryBuilder $w) => $w
                    ->where('m.batch_number', 'like', $like)
                    ->orWhere('m.notes', 'like', $like)
                    ->orWhere('m.regu', 'like', $like)
                    ->orWhere('u.name', 'like', $like)));
        }

        return $query->orderByDesc('date')->orderByDesc('id');
    }

    /** Rangkaian lolos bila setidaknya satu batchnya (awal atau lanjutan) memenuhi kondisi. */
    private static function anyMember(Builder $query, Closure $condition): Builder
    {
        return $query->whereExists(function (QueryBuilder $sub) use ($condition) {
            $sub->selectRaw('1')
                ->from('reclaimer_batches as m')
                ->whereNull('m.deleted_at')
                ->where(fn (QueryBuilder $w) => $w
                    ->whereColumn('m.id', 'reclaimer_batches.id')
                    ->orWhereColumn('m.parent_batch_id', 'reclaimer_batches.id'));
            $condition($sub);
        });
    }
}
