<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor Excel Laporan Reclaimer (FR-56). Angka disimpan sebagai angka, bukan teks,
 * supaya bisa langsung diolah; Excel menampilkannya sesuai format regional pengguna.
 */
final class ReclaimerReportExcel
{
    private const KG = '#,##0.00';

    private const PERCENT = '0.0"%"';

    private const HEADER_FILL = 'EAF3FB'; // brand-blue-50

    public function __construct(private readonly array $report) {}

    public function save(string $path): void
    {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle('Laporan Reclaimer')->setCreator('Material Balance & B3 Tracker');

        $this->summarySheet($book->getActiveSheet());
        $this->table($book->createSheet(), 'Per shift', ['Shift', 'Rangkaian', 'Batch', 'Bahan masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate'],
            array_map(fn ($s) => [$s['shift'], $s['chain_count'], $s['batch_count'], $s['input'], $s['recovery'], $s['residue'], $s['chain_count'] ? $s['recovery_rate'] : null], $this->report['by_shift']),
            [4 => self::KG, 5 => self::KG, 6 => self::KG, 7 => self::PERCENT]);

        if ($breakdown = $this->report['breakdown']) {
            $label = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$breakdown['unit']];
            $this->table($book->createSheet(), "Per {$label}", [$label, 'Rangkaian', 'Bahan masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate'],
                array_map(fn ($r) => [
                    $breakdown['unit'] === 'month' ? self::month($r['start']) : ($r['start'] === $r['end'] ? self::date($r['start']) : self::date($r['start']) . ' – ' . self::date($r['end'])),
                    $r['chain_count'], $r['input'], $r['recovery'], $r['residue'], $r['chain_count'] ? $r['recovery_rate'] : null,
                ], $breakdown['rows']),
                [3 => self::KG, 4 => self::KG, 5 => self::KG, 6 => self::PERCENT]);
        }

        $this->table($book->createSheet(), 'Rangkaian', ['No. batch awal', 'Batch lanjutan', 'Tanggal', 'Shift awal', 'Regu', 'Operator', 'Status', 'Tinta X (kg)', 'Solvent dipakai (kg)', 'Bahan masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate', 'Target saat selesai', 'Catatan tinjauan'],
            array_map(fn ($c) => [
                $c['batch_number'], implode(', ', array_slice($c['batch_numbers'], 1)), self::date($c['date']), $c['shift'], $c['regu'], $c['operator'],
                ! $c['is_complete'] ? 'Belum selesai' : ($c['is_below_target'] ? 'Di bawah target' : 'Memenuhi target'),
                $c['ink'], $c['solvent_used'], $c['input'], $c['recovery'], $c['is_complete'] ? $c['residue'] : null,
                $c['recovery_rate'], $c['target_recovery_rate'], $c['review'] ? "{$c['review']['by']}: {$c['review']['notes']}" : null,
            ], $this->report['chains']),
            [8 => self::KG, 9 => self::KG, 10 => self::KG, 11 => self::KG, 12 => self::KG, 13 => self::PERCENT, 14 => self::PERCENT]);

        $this->table($book->createSheet(), 'Di bawah target', ['No. batch awal', 'Tanggal', 'Shift awal', 'Recovery rate', 'Target saat selesai', 'Ditinjau oleh', 'Catatan tinjauan'],
            array_map(fn ($c) => [$c['batch_number'], self::date($c['date']), $c['shift'], $c['recovery_rate'], $c['target_recovery_rate'], $c['review']['by'] ?? 'Belum ditinjau', $c['review']['notes'] ?? null], $this->report['below_target']),
            [4 => self::PERCENT, 5 => self::PERCENT]);

        $this->table($book->createSheet(), 'Suhu tidak normal', ['No. batch', 'Tanggal', 'Shift', 'Oil Temperature (°C)', 'Vapour Temperature (°C)'],
            array_map(fn ($b) => [$b['batch_number'], self::date($b['date']), $b['shift'], $b['oil_temp'], $b['vapour_temp']], $this->report['temp_abnormal']));

        $this->table($book->createSheet(), 'Maintenance', ['Mulai', 'Selesai', 'Durasi di periode (jam)', 'Alasan', 'Catatan perbaikan', 'Dilaporkan oleh'],
            array_map(fn ($m) => [self::dateTime($m['start']), $m['end'] ? self::dateTime($m['end']) : 'Masih berlangsung', round($m['minutes_in_period'] / 60, 2), $m['reason'], $m['end_note'], $m['started_by']], $this->report['maintenance']['rows']),
            [3 => '#,##0.00']);

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
    }

    private function summarySheet(Worksheet $sheet): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $sheet->setTitle('Ringkasan');

        $rows = [
            ['Laporan Reclaimer ' . $r['period_label']],
            ['Periode', self::date($r['start']) . ($r['start'] === $r['end'] ? '' : ' – ' . self::date($r['end']))],
            ['Dibuat', self::dateTime($r['generated_at'])],
            [],
            ['Material balance (rangkaian selesai)'],
            ['Rangkaian selesai', $s['chain_count']],
            ['Batch', $s['batch_count']],
            ['Rangkaian belum selesai (tidak dihitung)', $s['open_chain_count']],
            ['Tinta X masuk (kg)', $s['ink']],
            ['Solvent dipakai (kg)', $s['solvent_used']],
            ['Bahan masuk (kg)', $s['input']],
            ['Hasil recovery (kg)', $s['recovery']],
            ['Residu estimasi (kg)', $s['residue']],
            ['Recovery rate', $s['chain_count'] ? $s['recovery_rate'] : null],
            ['Target recovery', $r['target_recovery_rate']],
            ['Rangkaian di bawah target', $s['below_target_count']],
            [],
            ['Saldo solvent'],
            ['Saldo awal (kg)', $r['solvent']['opening']],
            ['Solvent dipakai (kg)', -$r['solvent']['used']],
            ['Hasil recovery masuk (kg)', $r['solvent']['recovered']],
            ['Penyesuaian (kg)', $r['solvent']['adjustment']],
            [$r['solvent']['closing_is_current'] ? 'Saldo saat ini (kg)' : 'Saldo akhir (kg)', $r['solvent']['closing']],
            [],
            ['Maintenance mesin'],
            ['Jumlah maintenance', $r['maintenance']['count']],
            ['Total downtime di periode (jam)', round($r['maintenance']['total_minutes'] / 60, 2)],
        ];
        $sheet->fromArray($rows, null, 'A1');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        foreach ([5, 18, 25] as $row) {
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }
        foreach ([9, 10, 11, 12, 13, 19, 20, 21, 22, 23, 27] as $row) {
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::KG);
        }
        foreach ([14, 15] as $row) {
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::PERCENT);
        }
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(24);
    }

    /** @param  array<int, string>  $formats  kolom (mulai 1) => format angka */
    private function table(Worksheet $sheet, string $title, array $headers, array $rows, array $formats = []): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows ?: [['Tidak ada data di periode ini']], null, 'A2');

        $lastColumn = $sheet->getHighestColumn();
        $header = $sheet->getStyle("A1:{$lastColumn}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $sheet->freezePane('A2');

        $lastRow = max(2, count($rows) + 1);
        foreach ($formats as $column => $format) {
            $letter = chr(64 + $column);
            $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode($format);
        }
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(chr(64 + $column))->setAutoSize(true);
        }
    }

    public static function date(string $iso): string
    {
        return Carbon::parse($iso)->locale('id')->translatedFormat('j M Y');
    }

    public static function month(string $iso): string
    {
        return Carbon::parse($iso)->locale('id')->translatedFormat('F Y');
    }

    public static function dateTime(string $iso): string
    {
        return Carbon::parse($iso)->timezone(config('app.timezone'))->locale('id')->translatedFormat('j M Y, H.i');
    }
}
