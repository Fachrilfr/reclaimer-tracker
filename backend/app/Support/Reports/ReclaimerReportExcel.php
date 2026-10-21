<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor Excel Laporan Reclaimer (FR-56). Angka disimpan sebagai angka, bukan teks,
 * supaya bisa langsung diolah; Excel menampilkannya sesuai format regional pengguna.
 *
 * Tampilan mengikuti design.md: kop dengan logo, header tabel biru tua, garis tipis,
 * baris berselang, dan warna status hanya untuk kondisi yang perlu ditindaklanjuti
 * (selalu disertai teks, tidak mengandalkan warna saja).
 */
final class ReclaimerReportExcel
{
    private const KG = '#,##0.00';

    private const PERCENT = '0.0"%"';

    private const HOURS = '#,##0.00';

    // Token warna dari design.md
    private const BLUE_900 = '0A3557';

    private const BLUE_800 = '0F4A78';

    private const BLUE_600 = '1B75BB';

    private const BLUE_50 = 'EAF3FB';

    private const GRAY_25 = 'F7F9FC';

    private const GRAY_200 = 'DDE3EA';

    private const GRAY_600 = '5B6673';

    private const GRAY_800 = '2B333D';

    private const TONES = [
        'success' => ['157F3D', 'E8F6EE'],
        'warning' => ['9A6700', 'FFF6DB'],
        'urgent' => ['C2410C', 'FFEDE3'],
        'danger' => ['B42318', 'FDECEA'],
    ];

    /** Baris header tabel; baris 1-3 dipakai kop (logo, judul, periode). */
    public const HEADER_ROW = 5;

    public function __construct(private readonly array $report, private readonly ?string $generatedBy = null) {}

    public function save(string $path): void
    {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle('Laporan Reclaimer ' . $this->report['period_label'])->setCreator('Material Balance & B3 Tracker');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setRGB(self::GRAY_800);

        $r = $this->report;
        $s = $r['summary'];
        $target = (float) $r['target_recovery_rate'];
        $belowTarget = fn (?float $rate) => $rate !== null && $rate < $target ? 'danger' : null;

        $this->summarySheet($book->getActiveSheet());

        $this->table($book->createSheet(), 'Per shift', 'Material balance per shift awal rangkaian',
            ['Shift', 'Rangkaian', 'Batch', 'Tinta X masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate'],
            array_map(fn ($x) => [$x['shift'], $x['chain_count'], $x['batch_count'], $x['input'], $x['recovery'], $x['residue'], $x['chain_count'] ? $x['recovery_rate'] : null], $r['by_shift']),
            formats: [4 => self::KG, 5 => self::KG, 6 => self::KG, 7 => self::PERCENT],
            tones: [7 => fn ($row) => $belowTarget($row[6])],
            total: ['Total', $s['chain_count'], $s['batch_count'], $s['input'], $s['recovery'], $s['residue'], $s['chain_count'] ? $s['recovery_rate'] : null],
            empty: 'Belum ada rangkaian selesai di periode ini.');

        if ($breakdown = $r['breakdown']) {
            $label = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$breakdown['unit']];
            $this->table($book->createSheet(), "Per {$label}", "Material balance per " . strtolower($label),
                [$label, 'Rangkaian', 'Tinta X masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate'],
                array_map(fn ($x) => [
                    $breakdown['unit'] === 'month' ? self::month($x['start']) : ($x['start'] === $x['end'] ? self::date($x['start']) : self::date($x['start']) . ' – ' . self::date($x['end'])),
                    $x['chain_count'], $x['input'], $x['recovery'], $x['residue'], $x['chain_count'] ? $x['recovery_rate'] : null,
                ], $breakdown['rows']),
                formats: [3 => self::KG, 4 => self::KG, 5 => self::KG, 6 => self::PERCENT],
                tones: [6 => fn ($row) => $belowTarget($row[5])],
                total: ['Total', $s['chain_count'], $s['input'], $s['recovery'], $s['residue'], $s['chain_count'] ? $s['recovery_rate'] : null],
                empty: 'Belum ada rangkaian selesai di periode ini.');
        }

        $status = fn ($c) => ! $c['is_complete'] ? 'Belum selesai' : ($c['is_below_target'] ? 'Di bawah target' : 'Memenuhi target');
        $statusTone = ['Belum selesai' => 'warning', 'Di bawah target' => 'danger', 'Memenuhi target' => null];
        $this->table($book->createSheet(), 'Rangkaian', 'Semua rangkaian batch yang dimulai di periode ini',
            ['No. batch awal', 'Batch lanjutan', 'Tanggal', 'Shift awal', 'Regu', 'Operator', 'Status', 'Tinta X masuk (kg)', 'Recovery (kg)', 'Residu estimasi (kg)', 'Recovery rate', 'Target saat selesai', 'Catatan tinjauan'],
            array_map(fn ($c) => [
                $c['batch_number'], implode(', ', array_slice($c['batch_numbers'], 1)) ?: null, self::date($c['date']), $c['shift'], $c['regu'], $c['operator'],
                $status($c), $c['input'], $c['recovery'], $c['is_complete'] ? $c['residue'] : null,
                $c['recovery_rate'], $c['target_recovery_rate'], $c['review'] ? "{$c['review']['by']}: {$c['review']['notes']}" : null,
            ], $r['chains']),
            formats: [8 => self::KG, 9 => self::KG, 10 => self::KG, 11 => self::PERCENT, 12 => self::PERCENT],
            tones: [7 => fn ($row) => $statusTone[$row[6]], 11 => fn ($row) => $row[6] === 'Di bawah target' ? 'danger' : null],
            wrap: [2 => 24, 13 => 40],
            empty: 'Belum ada rangkaian batch di periode ini.');

        $usages = $r['solvent_usages'];
        $this->table($book->createSheet(), 'Pemakaian solvent', 'Solvent yang diambil dari saldo hasil recovery',
            ['Tanggal', 'Shift', 'Berat diambil (kg)', 'Keperluan', 'Catatan', 'Saldo sesudah (kg)', 'Dicatat oleh'],
            array_map(fn ($u) => [self::date($u['date']), $u['shift'], $u['weight'], $u['purpose'], $u['notes'], $u['balance_after'], $u['used_by']], $usages),
            formats: [3 => self::KG, 6 => self::KG],
            wrap: [4 => 36, 5 => 36],
            total: $usages ? ['Total', null, round(array_sum(array_column($usages, 'weight')), 2), null, null, null, null] : null,
            empty: 'Tidak ada solvent yang diambil dari saldo di periode ini.');

        $this->table($book->createSheet(), 'Di bawah target', "Rangkaian selesai dengan recovery rate di bawah target",
            ['No. batch awal', 'Tanggal', 'Shift awal', 'Recovery rate', 'Target saat selesai', 'Ditinjau oleh', 'Catatan tinjauan'],
            array_map(fn ($c) => [$c['batch_number'], self::date($c['date']), $c['shift'], $c['recovery_rate'], $c['target_recovery_rate'], $c['review']['by'] ?? 'Belum ditinjau', $c['review']['notes'] ?? null], $r['below_target']),
            formats: [4 => self::PERCENT, 5 => self::PERCENT],
            tones: [4 => fn () => 'danger', 6 => fn ($row) => $row[5] === 'Belum ditinjau' ? 'warning' : null],
            wrap: [7 => 48],
            empty: 'Semua rangkaian di periode ini memenuhi target recovery.',
            tab: $r['below_target'] ? self::TONES['danger'][0] : null);

        $this->table($book->createSheet(), 'Suhu tidak normal', 'Batch dengan suhu di luar batas normal',
            ['No. batch', 'Tanggal', 'Shift', 'Oil Temperature (°C)', 'Vapour Temperature (°C)'],
            array_map(fn ($b) => [$b['batch_number'], self::date($b['date']), $b['shift'], $b['oil_temp'], $b['vapour_temp']], $r['temp_abnormal']),
            formats: [4 => '0.0', 5 => '0.0'],
            empty: 'Tidak ada batch dengan suhu tidak normal di periode ini.',
            tab: $r['temp_abnormal'] ? self::TONES['urgent'][0] : null);

        $this->table($book->createSheet(), 'Maintenance', 'Riwayat maintenance mesin di periode ini',
            ['Mulai', 'Selesai', 'Durasi di periode (jam)', 'Alasan', 'Catatan perbaikan', 'Dilaporkan oleh'],
            array_map(fn ($m) => [self::dateTime($m['start']), $m['end'] ? self::dateTime($m['end']) : 'Masih berlangsung', round($m['minutes_in_period'] / 60, 2), $m['reason'], $m['end_note'], $m['started_by']], $r['maintenance']['rows']),
            formats: [3 => self::HOURS],
            tones: [2 => fn ($row) => $row[1] === 'Masih berlangsung' ? 'urgent' : null],
            wrap: [4 => 36, 5 => 36],
            total: ['Total', null, round($r['maintenance']['total_minutes'] / 60, 2), null, null, null],
            empty: 'Mesin tidak menjalani maintenance di periode ini.');

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
    }

    private function summarySheet(Worksheet $sheet): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $sheet->setTitle('Ringkasan');
        $sheet->getTabColor()->setRGB(self::BLUE_600);
        $this->letterhead($sheet, 'Ringkasan material balance, saldo solvent, dan maintenance', 3);

        $rate = $s['chain_count'] ? $s['recovery_rate'] : null;
        $isBelow = $rate !== null && $rate < $r['target_recovery_rate'];
        $closing = $r['solvent']['closing_is_current'] ? 'Saldo saat ini (kg)' : 'Saldo akhir (kg)';

        $usageCount = count($r['solvent_usages']);
        // Setiap baris: [kunci, label, nilai, keterangan, format angka]
        $sections = [
            ['Material balance (rangkaian selesai)', [
                ['chains', 'Rangkaian selesai', $s['chain_count'], null, null],
                ['batches', 'Batch', $s['batch_count'], null, null],
                ['open', 'Rangkaian belum selesai', $s['open_chain_count'], 'Tidak dihitung sampai batch terakhirnya disimpan selesai', null],
                ['input', 'Tinta X masuk (kg)', $s['input'], 'Bahan masuk ke mesin reclaimer', self::KG],
                ['recovery', 'Hasil recovery (kg)', $s['recovery'], 'Solvent yang dihasilkan dari Tinta X', self::KG],
                ['residue', 'Residu estimasi (kg)', $s['residue'], 'Tinta X − hasil recovery', self::KG],
                ['rate', 'Recovery rate', $rate, $rate === null ? 'Belum ada rangkaian selesai' : ($isBelow ? 'Di bawah target' : 'Memenuhi target') . ' · hasil recovery ÷ Tinta X', self::PERCENT],
                ['target', 'Target recovery', $r['target_recovery_rate'], null, self::PERCENT],
                ['below', 'Rangkaian di bawah target', $s['below_target_count'], $s['below_target_count'] ? 'Rincian di sheet "Di bawah target"' : null, null],
            ]],
            ['Saldo solvent', [
                ['opening', 'Saldo awal (kg)', $r['solvent']['opening'], null, self::KG],
                ['recovered', 'Hasil recovery masuk (kg)', $r['solvent']['recovered'], null, self::KG],
                ['used', 'Solvent dipakai (kg)', -$r['solvent']['used'], $usageCount ? "{$usageCount} kali diambil, rincian di sheet \"Pemakaian solvent\"" : 'Tidak ada pengambilan di periode ini', self::KG],
                ['adjustment', 'Penyesuaian (kg)', $r['solvent']['adjustment'], null, self::KG],
                ['closing', $closing, $r['solvent']['closing'], null, self::KG],
            ]],
            ['Maintenance mesin', [
                ['maintenance', 'Jumlah maintenance', $r['maintenance']['count'], null, null],
                ['downtime', 'Total downtime di periode (jam)', round($r['maintenance']['total_minutes'] / 60, 2), null, self::HOURS],
            ]],
        ];

        $row = self::HEADER_ROW;
        $at = [];
        foreach ($sections as [$title, $items]) {
            $sheet->fromArray([$title, 'Nilai', 'Keterangan'], null, "A{$row}");
            $this->headerStyle($sheet, "A{$row}:C{$row}");
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $first = $row + 1;
            foreach ($items as [$key, $label, $value, $note, $format]) {
                $row++;
                $at[$key] = $row;
                $sheet->fromArray([$label, $value, $note], null, "A{$row}", true);
                if ($format) {
                    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($format);
                }
            }
            $this->bodyStyle($sheet, "A{$first}:C{$row}");
            $sheet->getStyle("C{$first}:C{$row}")->getFont()->getColor()->setRGB(self::GRAY_600);
            $row += 2;
        }
        $lastRow = $row - 2;

        // Angka kunci laporan: recovery rate dan saldo akhir dicetak tebal
        $sheet->getStyle("A{$at['rate']}:B{$at['rate']}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$at['closing']}:B{$at['closing']}")->getFont()->setBold(true);
        $sheet->getStyle("A{$at['closing']}:C{$at['closing']}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRAY_600);
        if ($rate !== null) {
            $this->tone($sheet, "C{$at['rate']}", $isBelow ? 'danger' : 'success');
            if ($isBelow) {
                $sheet->getStyle("B{$at['rate']}")->getFont()->getColor()->setRGB(self::TONES['danger'][0]);
            }
        }
        if ($s['below_target_count']) {
            $this->tone($sheet, "B{$at['below']}", 'danger');
        }
        if ($s['open_chain_count']) {
            $this->tone($sheet, "B{$at['open']}", 'warning');
        }

        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(60);
        $this->pageSetup($sheet, "A1:C{$lastRow}", PageSetup::ORIENTATION_PORTRAIT);
    }

    /**
     * @param  array<int, string>  $formats  kolom (mulai 1) => format angka
     * @param  array<int, callable>  $tones  kolom => fn(array $row): ?string (success/warning/urgent/danger)
     * @param  array<int, int>  $wrap  kolom teks panjang => lebar tetap, teks dibungkus
     */
    private function table(Worksheet $sheet, string $title, string $subtitle, array $headers, array $rows,
        array $formats = [], array $tones = [], array $wrap = [], ?array $total = null, string $empty = '', ?string $tab = null): void
    {
        $sheet->setTitle($title);
        if ($tab) {
            $sheet->getTabColor()->setRGB($tab);
        }
        $columns = count($headers);
        $lastColumn = Coordinate::stringFromColumnIndex($columns);
        $this->letterhead($sheet, $subtitle, $columns);

        $head = self::HEADER_ROW;
        $first = $head + 1;
        $sheet->fromArray($headers, null, "A{$head}");
        $this->headerStyle($sheet, "A{$head}:{$lastColumn}{$head}");
        $sheet->freezePane('A' . $first);

        if (! $rows) {
            $sheet->setCellValue("A{$first}", $empty);
            $sheet->mergeCells("A{$first}:{$lastColumn}{$first}");
            $style = $sheet->getStyle("A{$first}:{$lastColumn}{$first}");
            $style->getFont()->setItalic(true)->getColor()->setRGB(self::GRAY_600);
            $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRAY_200);
            $sheet->getRowDimension($first)->setRowHeight(24);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->widths($sheet, $headers, [], $wrap);
            $this->pageSetup($sheet, "A1:{$lastColumn}{$first}");

            return;
        }

        $sheet->fromArray($rows, null, "A{$first}", true);
        // Header kolom angka rata kanan, segaris dengan angkanya
        foreach (array_keys($headers) as $i) {
            if (collect($rows)->contains(fn ($row) => is_int($row[$i] ?? null) || is_float($row[$i] ?? null))) {
                $sheet->getStyle(Coordinate::stringFromColumnIndex($i + 1) . $head)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $last = $head + count($rows);
        $this->bodyStyle($sheet, "A{$first}:{$lastColumn}{$last}");
        $sheet->setAutoFilter("A{$head}:{$lastColumn}{$last}");

        foreach ($formats as $column => $format) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $sheet->getStyle("{$letter}{$first}:{$letter}" . ($last + 1))->getNumberFormat()->setFormatCode($format);
        }
        foreach ($rows as $i => $row) {
            foreach ($tones as $column => $pick) {
                if ($tone = $pick(array_values($row))) {
                    $this->tone($sheet, Coordinate::stringFromColumnIndex($column) . ($first + $i), $tone);
                }
            }
        }
        foreach ($wrap as $column => $width) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $sheet->getStyle("{$letter}{$first}:{$letter}{$last}")->getAlignment()->setWrapText(true);
            // Baris dengan teks panjang memakai tinggi otomatis supaya teks tidak terpotong
            foreach ($rows as $i => $row) {
                if (mb_strlen((string) ($row[$column - 1] ?? '')) > $width - 2) {
                    $sheet->getRowDimension($first + $i)->setRowHeight(-1);
                }
            }
        }

        $end = $last;
        if ($total) {
            $end = $last + 1;
            $sheet->fromArray($total, null, "A{$end}", true);
            $style = $sheet->getStyle("A{$end}:{$lastColumn}{$end}");
            $style->getFont()->setBold(true)->getColor()->setRGB(self::BLUE_900);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BLUE_50);
            $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BLUE_800);
            $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRAY_200);
            $sheet->getRowDimension($end)->setRowHeight(20);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        }

        $this->widths($sheet, $headers, $rows, $wrap);
        $this->pageSetup($sheet, "A1:{$lastColumn}{$end}");
    }

    /** Kop setiap sheet: logo, judul laporan, periode dan pembuat. */
    private function letterhead(Worksheet $sheet, string $subtitle, int $columns): void
    {
        $r = $this->report;
        $sheet->setShowGridlines(false);

        $logo = new Drawing();
        $logo->setName('Logo Indofood CBP');
        $logo->setPath(resource_path('images/logo-indofood-cbp.png'));
        $logo->setHeight(28);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(2);
        $logo->setOffsetY(4);
        $logo->setWorksheet($sheet);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $period = self::date($r['start']) . ($r['start'] === $r['end'] ? '' : ' – ' . self::date($r['end']));
        $meta = "Periode {$period}  ·  Dibuat " . self::dateTime($r['generated_at']) . ($this->generatedBy ? " oleh {$this->generatedBy}" : '');

        $sheet->setCellValue('A2', "Laporan Reclaimer {$r['period_label']}  ·  {$subtitle}");
        $sheet->setCellValue('A3', $meta);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BLUE_900);
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB(self::GRAY_600);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Garis biru di bawah kop, sama dengan kop PDF
        $lastColumn = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A3:{$lastColumn}3")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BLUE_600);
        $sheet->getRowDimension(4)->setRowHeight(8);
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BLUE_800);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true)->setIndent(1);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BLUE_800);
        [, $row] = Coordinate::coordinateFromString(explode(':', $range)[0]);
        $sheet->getRowDimension((int) $row)->setRowHeight(30);
    }

    /** Garis tipis dan baris berselang supaya baris panjang mudah diikuti mata. */
    private function bodyStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRAY_200);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);

        [$from, $to] = explode(':', $range);
        [$startColumn, $startRow] = Coordinate::coordinateFromString($from);
        [$endColumn, $endRow] = Coordinate::coordinateFromString($to);
        for ($row = (int) $startRow; $row <= (int) $endRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(18);
            if (($row - (int) $startRow) % 2 === 1) {
                $sheet->getStyle("{$startColumn}{$row}:{$endColumn}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRAY_25);
            }
        }
    }

    private function tone(Worksheet $sheet, string $cell, string $tone): void
    {
        [$fg, $bg] = self::TONES[$tone];
        $style = $sheet->getStyle($cell);
        $style->getFont()->setBold(true)->getColor()->setRGB($fg);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    }

    /** Lebar kolom dari isi terpanjang (header atau data), dengan batas atas. */
    private function widths(Worksheet $sheet, array $headers, array $rows, array $wrap): void
    {
        foreach (array_values($headers) as $i => $header) {
            $column = $i + 1;
            $letter = Coordinate::stringFromColumnIndex($column);
            if (isset($wrap[$column])) {
                $sheet->getColumnDimension($letter)->setWidth($wrap[$column]);

                continue;
            }
            $longest = mb_strlen($header) * 0.9;
            foreach ($rows as $row) {
                $value = array_values($row)[$i] ?? '';
                $longest = max($longest, mb_strlen(is_float($value) ? number_format($value, 2) : (string) $value));
            }
            $sheet->getColumnDimension($letter)->setWidth(min(max($longest + 3, 11), 34));
        }
    }

    private function pageSetup(Worksheet $sheet, string $area, string $orientation = PageSetup::ORIENTATION_LANDSCAPE): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation($orientation)
            ->setFitToWidth(1)->setFitToHeight(0)->setPrintArea($area);
        if ($sheet->getTitle() !== 'Ringkasan') {
            $setup->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        }
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&L&8Laporan Reclaimer ' . $this->report['period_label'] . ' · ' . $sheet->getTitle() . '&R&8Halaman &P dari &N');
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
