<?php

namespace App\Support;

use App\Http\Controllers\Api\PalletController;
use App\Models\B3Pallet;
use App\Models\SuratJalan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard HSE untuk satu periode: berat residu dari input reclaimer (estimasi) dan
 * berat yang diserahkan ke vendor (timbangan vendor di surat jalan), plus pallet di TPS.
 *
 * Pallet tidak ditimbang di TPS, jadi berat residu di TPS hanya bisa didekati dari dua
 * sisi: estimasi dari input dan timbangan vendor saat serah terima.
 */
final class HseDashboardReport
{
    private readonly Carbon $start;

    private readonly Carbon $end;

    public function __construct(private readonly string $period, Carbon $anchor, ?Carbon $until = null)
    {
        [$this->start, $this->end] = ReportPeriod::range($period, $anchor, $until);
    }

    public function toArray(): array
    {
        // Residu baru pasti setelah rangkaian selesai, seperti di dashboard reclaimer
        $completed = DashboardReport::chainsBetween($this->start, $this->end)
            ->filter(fn (BatchChain $c) => $c->isComplete())->values();
        $suratJalan = SuratJalan::with('pallets')
            ->whereDate('handover_date', '>=', $this->start->toDateString())
            ->whereDate('handover_date', '<=', $this->end->toDateString())
            ->get();

        $thresholds = PalletController::thresholds();
        $tpsPallets = B3Pallet::where('status', '!=', 'transported')->whereNotNull('sent_to_tps_at')->orderBy('expiry_date')->get()
            ->map(fn (B3Pallet $pallet) => PalletController::present($pallet, $thresholds));
        $unit = ReportPeriod::BREAKDOWN[$this->period];

        return [
            'period' => $this->period,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'totals' => self::residue($completed) + self::handover($suratJalan) + [
                'tps_pallet_count' => $tpsPallets->count(),
                'tps_drum_count' => $tpsPallets->sum('drum_count'),
            ],
            'inputs' => $completed->map(fn (BatchChain $c) => [
                'root_id' => $c->root->id,
                'batch_number' => $c->root->batch_number,
                'batch_count' => $c->batches->count(),
                'date' => $c->root->date->toDateString(),
                'shift' => $c->root->shift?->name,
                'input' => $c->input(),
                'residue' => $c->residue(),
            ])->reverse()->values()->all(),
            'breakdown' => $unit ? [
                'unit' => $unit,
                'rows' => array_map(fn (array $b) => ['start' => $b['start'], 'end' => $b['end']]
                    + self::residue($completed->filter(fn (BatchChain $c) => $c->root->date->between($b['from'], $b['to'])))
                    + self::handover($suratJalan->filter(fn (SuratJalan $s) => $s->handover_date->between($b['from'], $b['to']))),
                    ReportPeriod::buckets($this->start, $this->end, $unit)),
            ] : null,
            'action_pallets' => $tpsPallets->filter(fn ($pallet) => $pallet['storage']['needs_action'])->values(),
            'tps_pallets' => $tpsPallets,
        ];
    }

    /** @param  Collection<int, BatchChain>  $chains */
    private static function residue(Collection $chains): array
    {
        $summary = BatchChain::summarize($chains);

        return [
            'chain_count' => $summary['chain_count'],
            'batch_count' => $summary['batch_count'],
            'input' => $summary['input'],
            'residue' => $summary['residue'],
        ];
    }

    /** @param  Collection<int, SuratJalan>  $suratJalan */
    private static function handover(Collection $suratJalan): array
    {
        return [
            'surat_jalan_count' => $suratJalan->count(),
            'handover_pallet_count' => $suratJalan->sum(fn (SuratJalan $s) => $s->pallets->count()),
            'vendor_weight' => round($suratJalan->sum('weight_kg'), 2),
        ];
    }
}
