<?php

namespace App\Support\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Ekspor Excel Laporan Limbah B3 (HSE). Gaya tampilan di ReportWorkbook. */
final class B3ReportExcel extends ReportWorkbook
{
    public const LOCATION = ['tps' => 'Di TPS', 'reclaimer' => 'Di area reclaimer'];

    public const LEVEL = ['aman' => 'Aman', 'perhatian' => 'Perhatian', 'mendesak' => 'Mendesak', 'kritis' => 'Kritis'];

    private const LEVEL_TONE = ['aman' => null, 'perhatian' => 'warning', 'mendesak' => 'urgent', 'kritis' => 'danger'];

    protected function title(): string
    {
        return 'Laporan Limbah B3';
    }

    protected function build(Spreadsheet $book): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $total = fn (string $label, array $t, int $blank = 0) => array_merge([$label], array_fill(0, $blank, null), [$t['surat_jalan_count'], $t['pallet_count'], $t['drum_count'], $t['weight_kg']]);

        $this->summarySheet($book->getActiveSheet());

        $this->table($book->createSheet(), 'Per vendor', 'Berat yang diserahkan per vendor',
            ['Vendor', 'Surat jalan', 'Pallet', 'Drum', 'Berat vendor (kg)'],
            array_map(fn ($v) => [$v['vendor'], $v['surat_jalan_count'], $v['pallet_count'], $v['drum_count'], $v['weight_kg']], $r['by_vendor']),
            formats: [5 => self::KG],
            total: $r['by_vendor'] ? $total('Total', $s) : null,
            empty: 'Tidak ada limbah B3 yang diserahkan ke vendor di periode ini.');

        if ($breakdown = $r['breakdown']) {
            $label = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$breakdown['unit']];
            $this->table($book->createSheet(), "Per {$label}", 'Berat yang diserahkan per ' . strtolower($label),
                [$label, 'Surat jalan', 'Pallet', 'Drum', 'Berat vendor (kg)'],
                array_map(fn ($x) => [self::bucketLabel($x, $breakdown['unit']), $x['surat_jalan_count'], $x['pallet_count'], $x['drum_count'], $x['weight_kg']], $breakdown['rows']),
                formats: [5 => self::KG],
                total: $total('Total', $s));
        }

        $this->table($book->createSheet(), 'Surat jalan', 'Surat jalan yang diserahkan di periode ini',
            ['No. surat jalan', 'Tanggal serah terima', 'Vendor', 'Pallet', 'Drum', 'Berat vendor (kg)', 'Petugas vendor', 'Dicatat oleh', 'Berkas bukti', 'Catatan'],
            array_map(fn ($sj) => [
                $sj['number'], self::date($sj['handover_date']), $sj['vendor'], implode(', ', array_column($sj['pallets'], 'pallet_number')),
                array_sum(array_column($sj['pallets'], 'drum_count')), $sj['weight_kg'], $sj['vendor_officer_name'], $sj['created_by'], $sj['file_name'], $sj['notes'],
            ], $r['surat_jalan']),
            formats: [6 => self::KG],
            wrap: [4 => 30, 10 => 36],
            total: $r['surat_jalan'] ? ['Total', null, null, null, $s['drum_count'], $s['weight_kg'], null, null, null, null] : null,
            empty: 'Belum ada surat jalan di periode ini.');

        $this->table($book->createSheet(), 'Masa simpan', "Lama simpan pallet yang diserahkan, batas {$r['max_storage_days']} hari sejak mulai terisi",
            ['No. pallet', 'Surat jalan', 'Mulai terisi', 'Sampai di TPS', 'Serah terima', 'Batas simpan', 'Lama simpan (hari)', 'Status'],
            array_map(fn ($c) => [
                $c['pallet_number'], $c['surat_jalan_number'], self::date($c['start_date']), $c['sent_to_tps_at'] ? self::date($c['sent_to_tps_at']) : null,
                self::date($c['handover_date']), self::date($c['expiry_date']), $c['days_stored'],
                $c['over_days'] > 0 ? "Lewat batas {$c['over_days']} hari" : 'Dalam batas',
            ], $r['compliance']),
            tones: [8 => fn ($row) => str_starts_with($row[7], 'Lewat') ? 'danger' : null],
            empty: 'Tidak ada pallet yang diserahkan di periode ini.',
            tab: $s['over_limit_count'] ? self::TONES['danger'][0] : null);

        $stock = $r['stock'];
        $this->table($book->createSheet(), 'Stok akhir periode', 'Pallet yang masih tersimpan per ' . self::date($stock['as_of']),
            ['No. pallet', 'Lokasi', 'Drum', 'Mulai terisi', 'Sampai di TPS', 'Batas simpan', 'Sisa masa simpan (hari)', 'Status'],
            array_map(fn ($p) => [
                $p['pallet_number'], self::LOCATION[$p['location']], $p['drum_count'], self::date($p['start_date']),
                $p['sent_to_tps_at'] ? self::date($p['sent_to_tps_at']) : null, self::date($p['expiry_date']), $p['days_remaining'], self::LEVEL[$p['level']],
            ], $stock['rows']),
            tones: [8 => fn ($row) => self::LEVEL_TONE[array_search($row[7], self::LEVEL, true)] ?? null, 7 => fn ($row) => $row[6] < 0 ? 'danger' : null],
            empty: 'Tidak ada pallet yang tersimpan pada akhir periode ini.');
    }

    private function summarySheet(Worksheet $sheet): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $rows = collect($r['stock']['rows']);
        $atTps = $rows->where('location', 'tps')->count();
        $needsAction = $rows->whereIn('level', ['mendesak', 'kritis'])->count();
        $stockLabel = $r['stock']['is_current'] ? 'hari ini' : 'per ' . self::date($r['stock']['as_of']);

        $at = $this->sectionsSheet($sheet, 'Ringkasan', 'Penyerahan ke vendor, kepatuhan masa simpan, dan stok', [
            ['Penyerahan ke vendor', [
                ['sj', 'Surat jalan', $s['surat_jalan_count'], null, null],
                ['pallets', 'Pallet diserahkan', $s['pallet_count'], null, null],
                ['drums', 'Drum diserahkan', $s['drum_count'], null, null],
                ['weight', 'Berat timbangan vendor (kg)', $s['weight_kg'], 'Berat resmi, dicatat per surat jalan', self::KG],
            ]],
            ['Kepatuhan masa simpan', [
                ['limit', 'Batas masa simpan (hari)', $r['max_storage_days'], 'Sejak pallet mulai terisi', null],
                ['over', 'Pallet diserahkan lewat batas', $s['over_limit_count'], $s['over_limit_count'] ? 'Rincian di sheet "Masa simpan"' : 'Semua pallet diserahkan dalam batas', null],
                ['longest', 'Lama simpan terlama (hari)', $s['longest_days_stored'], null, null],
            ]],
            ["Stok {$stockLabel}", [
                ['stock', 'Pallet masih tersimpan', $rows->count(), null, null],
                ['tps', 'Di TPS', $atTps, 'Menunggu diserahkan ke vendor', null],
                ['reclaimer', 'Di area reclaimer', $rows->count() - $atTps, 'Masih diisi', null],
                ['action', 'Mendesak atau kritis', $needsAction, $needsAction ? 'Sisa masa simpan 14 hari atau kurang' : null, null],
            ]],
        ]);

        $sheet->getStyle("A{$at['weight']}:B{$at['weight']}")->getFont()->setBold(true)->setSize(11);
        $this->tone($sheet, "B{$at['over']}", $s['over_limit_count'] ? 'danger' : 'success');
        if ($needsAction) {
            $this->tone($sheet, "B{$at['action']}", 'danger');
        }
    }
}
