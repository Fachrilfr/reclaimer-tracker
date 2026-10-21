<?php

namespace App\Support;

use App\Http\Controllers\Api\PalletController;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\Parameter;
use App\Models\Shift;
use App\Models\SolventLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi dashboard (FR-45 s/d FR-50) untuk satu periode: harian, bulanan, atau tahunan.
 *
 * Agregat dihitung per rangkaian batch yang sudah selesai (BatchChain), satu kali,
 * pada tanggal dan shift batch awalnya. Rangkaian yang belum selesai hanya ditampilkan
 * dan dihitung jumlahnya. Persentase dari jumlah total, bukan rata-rata (PRD §8).
 */
final class DashboardReport
{
    private readonly Carbon $start;

    private readonly Carbon $end;

    private readonly float $target;

    public function __construct(private readonly string $period, private readonly Carbon $anchor)
    {
        [$this->start, $this->end] = match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
        };
        $this->target = Parameter::number('target_recovery_rate');
    }

    public function toArray(): array
    {
        $chains = self::chainsBetween($this->start, $this->end);
        $completed = $chains->filter(fn (BatchChain $c) => $c->isComplete())->values();
        $totals = BatchChain::summarize($completed);

        $thresholds = PalletController::thresholds();
        // Masa simpan di TPS sampai diserahkan ke vendor (HSE)
        $activePallets = B3Pallet::where('status', '!=', 'transported')->orderBy('expiry_date')->get()
            ->map(fn (B3Pallet $pallet) => PalletController::present($pallet, $thresholds));
        // Pallet yang masih di area reclaimer, belum dikirim ke TPS (operator)
        $tpsPallets = B3Pallet::whereNull('sent_to_tps_at')->orderBy('tps_due_date')->get()
            ->map(fn (B3Pallet $pallet) => PalletController::present($pallet, $thresholds));

        // Saldo pada akhir periode; untuk periode yang masih berjalan, saldo saat ini
        $balanceAt = $this->end->isFuture() ? now() : $this->end;

        return [
            'period' => $this->period,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'target_recovery_rate' => $this->target,
            'totals' => $totals + [
                'open_chain_count' => $chains->count() - $completed->count(),
                'solvent_balance' => SolventLedger::balanceAt($balanceAt),
            ],
            // Alert toleransi recovery: recovery gabungan periode dibanding target
            'tolerance' => [
                'has_data' => $completed->isNotEmpty(),
                'is_below' => $completed->isNotEmpty() && $totals['recovery_rate'] < $this->target,
                'recovery_rate' => $totals['recovery_rate'],
                'gap' => round($totals['recovery_rate'] - $this->target, 2),
                'below_target_count' => $totals['below_target_count'],
                'chain_count' => $totals['chain_count'],
            ],
            'by_shift' => $this->byShift($completed),
            'breakdown' => $this->breakdown($chains, $completed),
            'trend' => $this->trend(),
            'action_items' => [
                'pallets' => $activePallets->filter(fn ($pallet) => $pallet['storage']['needs_action'])->values(),
                'tps_pallets' => $tpsPallets->filter(fn ($pallet) => $pallet['tps']['needs_action'])->values(),
                'low_recovery_chains' => $this->lowRecoveryChains(),
            ],
            'active_pallets' => $activePallets,
            // Saat maintenance, pengiriman residu ke TPS ikut terhenti (batas kirim tetap berjalan)
            'machine' => Machine::main()?->load('reporter')->summary(),
            'tps_pallets' => $tpsPallets,
        ];
    }

    /** @return Collection<int, BatchChain> rangkaian dengan batch awal bertanggal di rentang ini */
    private static function chainsBetween(Carbon $from, Carbon $to): Collection
    {
        return BatchChain::whereRoot(fn (Builder $q) => $q
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')->orderBy('id'));
    }

    private function byShift(Collection $completed): array
    {
        return Shift::where('is_active', true)->orderBy('id')->get()
            ->map(fn (Shift $shift) => ['shift' => $shift->name]
                + BatchChain::summarize($completed->filter(fn (BatchChain $c) => $c->root->shift_id === $shift->id)))
            ->all();
    }

    /**
     * FR-49: harian per rangkaian (termasuk yang belum selesai), bulanan per minggu,
     * tahunan per bulan (hanya rangkaian selesai).
     */
    private function breakdown(Collection $chains, Collection $completed): array
    {
        if ($this->period === 'day') {
            return ['unit' => 'chain', 'rows' => $chains->map(fn (BatchChain $c) => $c->toArray())->values()->all()];
        }

        $unit = $this->period === 'month' ? 'week' : 'month';

        return ['unit' => $unit, 'rows' => BatchChain::buckets($completed, $this->start, $this->end, $unit)];
    }

    /**
     * FR-48: tren recovery rate dan residu. Harian: 14 hari sampai tanggal dipilih;
     * bulanan: per hari dalam bulan; tahunan: per bulan.
     */
    private function trend(): array
    {
        [$from, $to, $unit] = match ($this->period) {
            'day' => [$this->anchor->copy()->subDays(13)->startOfDay(), $this->end, 'day'],
            'month' => [$this->start, $this->end, 'day'],
            'year' => [$this->start, $this->end, 'month'],
        };

        $completed = self::chainsBetween($from, $to)->filter(fn (BatchChain $c) => $c->isComplete());

        return [
            'unit' => $unit,
            'target_recovery_rate' => $this->target,
            // Titik dengan chain_count 0 tidak punya recovery rate dan tidak digambar
            'points' => BatchChain::buckets($completed, $from, $to, $unit),
        ];
    }

    /**
     * FR-45: rangkaian yang selesai dalam 14 hari terakhir dengan recovery gabungan di bawah
     * target dan belum ditinjau supervisor, terlepas dari periode yang dipilih. Batch yang
     * belum selesai tidak pernah masuk; yang tidak ditinjau hilang sendiri setelah 14 hari.
     */
    private function lowRecoveryChains(): array
    {
        return BatchChain::whereRoot(fn (Builder $q) => $q
            ->whereNotNull('chain_completed_at')
            ->where('chain_completed_at', '>=', now()->subDays(13)->startOfDay())
            ->where('is_recovery_low', true)
            // Sudah ditinjau supervisor: tidak perlu ditindak lagi
            ->where('supervisor_reviewed', false)
            ->orderByDesc('chain_completed_at'))
            ->map(fn (BatchChain $c) => $c->toArray())
            ->values()
            ->all();
    }
}
