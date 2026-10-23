<?php

namespace App\Support\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Ekspor Excel Laporan Reclaimer (FR-56). Gaya tampilan di ReportWorkbook. */
final class ReclaimerReportExcel extends ReportWorkbook
{
    protected function title(): string
    {
        return 'Laporan Reclaimer';
    }

    protected function build(Spreadsheet $book): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $target = (float) $r['target_recovery_rate'];
        $belowTarget = fn (?float $rate) => $rate !== null && $rate < $target ? 'danger' : null;

        $this->summarySheet($book->getActiveSheet());

        $this->table($book->createSheet(), 'Per shift', 'Material balance per shift awal rangkaian',
            ['Shift', 'Rangkaian', 'Batch', 'Tinta X masuk (kg)', 'Hasil solvent (kg)', 'Residu estimasi (kg)', 'Persentase solvent'],
            array_map(fn ($x) => [$x['shift'], $x['chain_count'], $x['batch_count'], $x['input'], $x['recovery'], $x['residue'], $x['chain_count'] ? $x['recovery_rate'] : null], $r['by_shift']),
            formats: [4 => self::KG, 5 => self::KG, 6 => self::KG, 7 => self::PERCENT],
            tones: [7 => fn ($row) => $belowTarget($row[6])],
            total: ['Total', $s['chain_count'], $s['batch_count'], $s['input'], $s['recovery'], $s['residue'], $s['chain_count'] ? $s['recovery_rate'] : null],
            empty: 'Belum ada rangkaian selesai di periode ini.');

        if ($breakdown = $r['breakdown']) {
            $label = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$breakdown['unit']];
            $this->table($book->createSheet(), "Per {$label}", 'Material balance per ' . strtolower($label),
                [$label, 'Rangkaian', 'Tinta X masuk (kg)', 'Hasil solvent (kg)', 'Residu estimasi (kg)', 'Persentase solvent'],
                array_map(fn ($x) => [
                    self::bucketLabel($x, $breakdown['unit']),
                    $x['chain_count'], $x['input'], $x['recovery'], $x['residue'], $x['chain_count'] ? $x['recovery_rate'] : null,
                ], $breakdown['rows']),
                formats: [3 => self::KG, 4 => self::KG, 5 => self::KG, 6 => self::PERCENT],
                tones: [6 => fn ($row) => $belowTarget($row[5])],
                total: ['Total', $s['chain_count'], $s['input'], $s['recovery'], $s['residue'], $s['chain_count'] ? $s['recovery_rate'] : null],
                empty: 'Belum ada rangkaian selesai di periode ini.');
        }

        $status = fn ($c) => ! $c['is_complete'] ? 'Belum selesai' : ($c['is_below_target'] ? 'Di bawah toleransi' : 'Memenuhi toleransi');
        $statusTone = ['Belum selesai' => 'warning', 'Di bawah toleransi' => 'danger', 'Memenuhi toleransi' => null];
        $this->table($book->createSheet(), 'Rangkaian', 'Semua rangkaian batch yang dimulai di periode ini',
            ['No. batch awal', 'Batch lanjutan', 'Tanggal', 'Shift awal', 'Regu', 'Operator', 'Status', 'Tinta X masuk (kg)', 'Hasil solvent (kg)', 'Residu estimasi (kg)', 'Persentase solvent', 'Toleransi saat selesai', 'Catatan tinjauan'],
            array_map(fn ($c) => [
                $c['batch_number'], implode(', ', array_slice($c['batch_numbers'], 1)) ?: null, self::date($c['date']), $c['shift'], $c['regu'], $c['operator'],
                $status($c), $c['input'], $c['recovery'], $c['is_complete'] ? $c['residue'] : null,
                $c['recovery_rate'], $c['target_recovery_rate'], $c['review'] ? "{$c['review']['by']}: {$c['review']['notes']}" : null,
            ], $r['chains']),
            formats: [8 => self::KG, 9 => self::KG, 10 => self::KG, 11 => self::PERCENT, 12 => self::PERCENT],
            tones: [7 => fn ($row) => $statusTone[$row[6]], 11 => fn ($row) => $row[6] === 'Di bawah toleransi' ? 'danger' : null],
            wrap: [2 => 24, 13 => 40],
            empty: 'Belum ada rangkaian batch di periode ini.');

        $usages = $r['solvent_usages'];
        $this->table($book->createSheet(), 'Pemakaian solvent', 'Solvent yang diambil dari saldo hasil solvent',
            ['Tanggal', 'Shift', 'Berat diambil (kg)', 'Keperluan', 'Catatan', 'Saldo sesudah (kg)', 'Dicatat oleh'],
            array_map(fn ($u) => [self::date($u['date']), $u['shift'], $u['weight'], $u['purpose'], $u['notes'], $u['balance_after'], $u['used_by']], $usages),
            formats: [3 => self::KG, 6 => self::KG],
            wrap: [4 => 36, 5 => 36],
            total: $usages ? ['Total', null, round(array_sum(array_column($usages, 'weight')), 2), null, null, null, null] : null,
            empty: 'Tidak ada solvent yang diambil dari saldo di periode ini.');

        $this->table($book->createSheet(), 'Di bawah toleransi', 'Rangkaian selesai dengan persentase solvent di bawah toleransi',
            ['No. batch awal', 'Tanggal', 'Shift awal', 'Persentase solvent', 'Toleransi saat selesai', 'Ditinjau oleh', 'Catatan tinjauan'],
            array_map(fn ($c) => [$c['batch_number'], self::date($c['date']), $c['shift'], $c['recovery_rate'], $c['target_recovery_rate'], $c['review']['by'] ?? 'Belum ditinjau', $c['review']['notes'] ?? null], $r['below_target']),
            formats: [4 => self::PERCENT, 5 => self::PERCENT],
            tones: [4 => fn () => 'danger', 6 => fn ($row) => $row[5] === 'Belum ditinjau' ? 'warning' : null],
            wrap: [7 => 48],
            empty: 'Semua rangkaian di periode ini memenuhi toleransi solvent.',
            tab: $r['below_target'] ? self::TONES['danger'][0] : null);

        $this->table($book->createSheet(), 'Suhu tidak normal', 'Batch dengan suhu di luar batas normal',
            ['No. batch', 'Tanggal', 'Shift', 'Suhu oli (°C)', 'Suhu uap (°C)'],
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
    }

    private function summarySheet(Worksheet $sheet): void
    {
        $r = $this->report;
        $s = $r['summary'];
        $rate = $s['chain_count'] ? $s['recovery_rate'] : null;
        $isBelow = $rate !== null && $rate < $r['target_recovery_rate'];
        $closing = $r['solvent']['closing_is_current'] ? 'Saldo saat ini (kg)' : 'Saldo akhir (kg)';
        $usageCount = count($r['solvent_usages']);

        $at = $this->sectionsSheet($sheet, 'Ringkasan', 'Ringkasan material balance, saldo solvent, dan maintenance', [
            ['Material balance (rangkaian selesai)', [
                ['chains', 'Rangkaian selesai', $s['chain_count'], null, null],
                ['batches', 'Batch', $s['batch_count'], null, null],
                ['open', 'Rangkaian belum selesai', $s['open_chain_count'], 'Tidak dihitung sampai batch terakhirnya disimpan selesai', null],
                ['input', 'Tinta X masuk (kg)', $s['input'], 'Bahan masuk ke mesin reclaimer', self::KG],
                ['recovery', 'Hasil solvent (kg)', $s['recovery'], 'Solvent yang dihasilkan dari Tinta X', self::KG],
                ['residue', 'Residu estimasi (kg)', $s['residue'], 'Tinta X − hasil solvent', self::KG],
                ['rate', 'Persentase solvent', $rate, $rate === null ? 'Belum ada rangkaian selesai' : ($isBelow ? 'Di bawah toleransi' : 'Memenuhi toleransi') . ' · hasil solvent ÷ Tinta X', self::PERCENT],
                ['target', 'Toleransi solvent', $r['target_recovery_rate'], null, self::PERCENT],
                ['below', 'Rangkaian di bawah toleransi', $s['below_target_count'], $s['below_target_count'] ? 'Rincian di sheet "Di bawah toleransi"' : null, null],
            ]],
            ['Saldo solvent', [
                ['opening', 'Saldo awal (kg)', $r['solvent']['opening'], null, self::KG],
                ['recovered', 'Hasil solvent masuk (kg)', $r['solvent']['recovered'], null, self::KG],
                ['used', 'Solvent dipakai (kg)', -$r['solvent']['used'], $usageCount ? "{$usageCount} kali diambil, rincian di sheet \"Pemakaian solvent\"" : 'Tidak ada pengambilan di periode ini', self::KG],
                ['adjustment', 'Penyesuaian (kg)', $r['solvent']['adjustment'], null, self::KG],
                ['closing', $closing, $r['solvent']['closing'], null, self::KG],
            ]],
            ['Maintenance mesin', [
                ['maintenance', 'Jumlah maintenance', $r['maintenance']['count'], null, null],
                ['downtime', 'Total downtime di periode (jam)', round($r['maintenance']['total_minutes'] / 60, 2), null, self::HOURS],
            ]],
        ]);

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
    }
}
