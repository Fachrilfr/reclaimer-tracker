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
 * Dasar ekspor Excel laporan (FR-56). Angka disimpan sebagai angka, bukan teks,
 * supaya bisa langsung diolah; Excel menampilkannya sesuai format regional pengguna.
 *
 * Tampilan mengikuti design.md: kop dengan logo, header tabel biru tua, garis tipis,
 * baris berselang, dan warna status hanya untuk kondisi yang perlu ditindaklanjuti
 * (selalu disertai teks, tidak mengandalkan warna saja).
 */
abstract class ReportWorkbook
{
    protected const KG = '#,##0.00';

    protected const PERCENT = '0.0"%"';

    protected const HOURS = '#,##0.00';

    // Token warna dari design.md
    protected const BLUE_900 = '0A3557';

    protected const BLUE_800 = '0F4A78';

    protected const BLUE_600 = '1B75BB';

    protected const BLUE_50 = 'EAF3FB';

    protected const GRAY_25 = 'F7F9FC';

    protected const GRAY_200 = 'DDE3EA';

    protected const GRAY_600 = '5B6673';

    protected const GRAY_800 = '2B333D';

    protected const TONES = [
        'success' => ['157F3D', 'E8F6EE'],
        'warning' => ['9A6700', 'FFF6DB'],
        'urgent' => ['C2410C', 'FFEDE3'],
        'danger' => ['B42318', 'FDECEA'],
    ];

    /** Baris header tabel; baris 1-3 dipakai kop (logo, judul, periode). */
    public const HEADER_ROW = 5;

    public function __construct(protected readonly array $report, protected readonly ?string $generatedBy = null) {}

    /** Nama laporan di kop dan kaki halaman, misalnya "Laporan Reclaimer". */
    abstract protected function title(): string;

    abstract protected function build(Spreadsheet $book): void;

    public function save(string $path): void
    {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle($this->title() . ' ' . $this->report['period_label'])->setCreator('Material Balance & B3 Tracker');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setRGB(self::GRAY_800);

        $this->build($book);

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
    }

    /**
     * Sheet ringkasan berisi beberapa bagian [judul, baris], tiap baris
     * [kunci, label, nilai, keterangan, format angka]. Mengembalikan kunci => nomor baris.
     *
     * @return array<string, int>
     */
    protected function sectionsSheet(Worksheet $sheet, string $title, string $subtitle, array $sections): array
    {
        $sheet->setTitle($title);
        $sheet->getTabColor()->setRGB(self::BLUE_600);
        $this->letterhead($sheet, $subtitle, 3);

        $row = self::HEADER_ROW;
        $at = [];
        foreach ($sections as [$heading, $items]) {
            $sheet->fromArray([$heading, 'Nilai', 'Keterangan'], null, "A{$row}");
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

        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(60);
        $this->pageSetup($sheet, 'A1:C' . ($row - 2), PageSetup::ORIENTATION_PORTRAIT);

        return $at;
    }

    /**
     * @param  array<int, string>  $formats  kolom (mulai 1) => format angka
     * @param  array<int, callable>  $tones  kolom => fn(array $row): ?string (success/warning/urgent/danger)
     * @param  array<int, int>  $wrap  kolom teks panjang => lebar tetap, teks dibungkus
     */
    protected function table(Worksheet $sheet, string $title, string $subtitle, array $headers, array $rows,
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
    protected function letterhead(Worksheet $sheet, string $subtitle, int $columns): void
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

        $sheet->setCellValue('A2', "{$this->title()} {$r['period_label']}  ·  {$subtitle}");
        $sheet->setCellValue('A3', $meta);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BLUE_900);
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB(self::GRAY_600);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Garis biru di bawah kop, sama dengan kop PDF
        $lastColumn = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A3:{$lastColumn}3")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BLUE_600);
        $sheet->getRowDimension(4)->setRowHeight(8);
    }

    protected function headerStyle(Worksheet $sheet, string $range): void
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
    protected function bodyStyle(Worksheet $sheet, string $range): void
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

    protected function tone(Worksheet $sheet, string $cell, string $tone): void
    {
        [$fg, $bg] = self::TONES[$tone];
        $style = $sheet->getStyle($cell);
        $style->getFont()->setBold(true)->getColor()->setRGB($fg);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    }

    /** Lebar kolom dari isi terpanjang (header atau data), dengan batas atas. */
    protected function widths(Worksheet $sheet, array $headers, array $rows, array $wrap): void
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

    protected function pageSetup(Worksheet $sheet, string $area, string $orientation = PageSetup::ORIENTATION_LANDSCAPE): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation($orientation)
            ->setFitToWidth(1)->setFitToHeight(0)->setPrintArea($area);
        if ($sheet->getTitle() !== 'Ringkasan') {
            $setup->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);
        }
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter("&L&8{$this->title()} {$this->report['period_label']} · {$sheet->getTitle()}&R&8Halaman &P dari &N");
    }

    /** Label baris rincian per hari, rentang minggu, atau bulan. */
    protected static function bucketLabel(array $row, string $unit): string
    {
        if ($unit === 'month') {
            return self::month($row['start']);
        }

        return $row['start'] === $row['end'] ? self::date($row['start']) : self::date($row['start']) . ' – ' . self::date($row['end']);
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
