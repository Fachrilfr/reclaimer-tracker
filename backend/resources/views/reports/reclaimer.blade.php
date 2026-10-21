@php
    use App\Support\Reports\ReclaimerReportExcel as Fmt;
    $kg = fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '.');
    $pct = fn ($v) => $v === null ? '–' : number_format($v, 1, ',', '.') . '%';
    $s = $report['summary'];
    $period = Fmt::date($report['start']) . ($report['start'] === $report['end'] ? '' : ' – ' . Fmt::date($report['end']));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Reclaimer {{ $report['period_label'] }}</title>
<style>
    @page { margin: 22mm 14mm 16mm 14mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #2B333D; }
    header { position: fixed; top: -16mm; left: 0; right: 0; height: 12mm; border-bottom: 2px solid #1B75BB; }
    header img { height: 7mm; }
    header .title { position: absolute; right: 0; top: 1mm; text-align: right; font-size: 8pt; color: #5B6673; }
    footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #5B6673; }
    h1 { font-size: 15pt; color: #141A21; margin: 0 0 2mm; }
    h2 { font-size: 10.5pt; color: #141A21; margin: 6mm 0 2mm; }
    .meta { color: #5B6673; margin-bottom: 4mm; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F7F9FC; color: #5B6673; font-weight: bold; text-align: left; padding: 1.6mm 2mm; border-bottom: 1px solid #DDE3EA; }
    td { padding: 1.4mm 2mm; border-bottom: 1px solid #EEF1F5; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .bad { color: #B42318; font-weight: bold; }
    .muted { color: #5B6673; }
    .kpi td { border: none; padding: 0 3mm 0 0; width: 25%; }
    .kpi .box { border: 1px solid #DDE3EA; border-radius: 2mm; padding: 2.5mm 3mm; }
    .kpi .label { color: #5B6673; font-size: 7.5pt; }
    .kpi .value { font-size: 13pt; font-weight: bold; color: #141A21; }
    .two td.col { width: 50%; border: none; padding: 0 3mm 0 0; vertical-align: top; }
    .empty { color: #5B6673; padding: 2mm 0; }
    .page-break { page-break-before: always; }
    /* Header tabel diulang tiap halaman; baris dan bagian pendek tidak terbelah */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    h2 { page-break-after: avoid; }
    .keep { page-break-inside: avoid; }
</style>
</head>
<body>
<header>
    <img src="{{ $logo }}" alt="Indofood CBP">
    <div class="title">Material Balance &amp; B3 Tracker<br>Laporan Reclaimer {{ $report['period_label'] }} · {{ $period }}</div>
</header>
<footer>Dibuat {{ Fmt::dateTime($report['generated_at']) }} WIB oleh {{ $generatedBy }} · Angka dari rangkaian batch yang sudah selesai, dihitung pada tanggal dan shift batch awalnya.</footer>

<h1>Laporan Reclaimer {{ $report['period_label'] }}</h1>
<div class="meta">Periode {{ $period }} · {{ $s['chain_count'] }} rangkaian selesai ({{ $s['batch_count'] }} batch)@if ($s['open_chain_count']) · {{ $s['open_chain_count'] }} rangkaian belum selesai tidak dihitung @endif</div>

<table class="kpi"><tr>
    <td><div class="box"><div class="label">Bahan masuk</div><div class="value">{{ $kg($s['input']) }} kg</div><div class="muted">Tinta X {{ $kg($s['ink']) }} + solvent {{ $kg($s['solvent_used']) }}</div></div></td>
    <td><div class="box"><div class="label">Hasil recovery</div><div class="value">{{ $kg($s['recovery']) }} kg</div><div class="muted">Residu estimasi {{ $kg($s['residue']) }} kg</div></div></td>
    <td><div class="box"><div class="label">Recovery rate</div><div class="value {{ $s['chain_count'] && $s['recovery_rate'] < $report['target_recovery_rate'] ? 'bad' : '' }}">{{ $s['chain_count'] ? $pct($s['recovery_rate']) : '–' }}</div><div class="muted">Target {{ $pct($report['target_recovery_rate']) }} · {{ $s['below_target_count'] }} rangkaian di bawah target</div></div></td>
    <td><div class="box"><div class="label">{{ $report['solvent']['closing_is_current'] ? 'Saldo solvent saat ini' : 'Saldo solvent akhir' }}</div><div class="value">{{ $kg($report['solvent']['closing']) }} kg</div><div class="muted">Saldo awal {{ $kg($report['solvent']['opening']) }} kg</div></div></td>
</tr></table>

<table class="two"><tr>
<td class="col">
    <h2>Saldo solvent</h2>
    <div class="muted">Bergerak per batch, termasuk batch yang rangkaiannya belum selesai.</div>
    <table>
        <tr><td>Saldo awal</td><td class="num">{{ $kg($report['solvent']['opening']) }} kg</td></tr>
        <tr><td>Solvent dipakai</td><td class="num">−{{ $kg($report['solvent']['used']) }} kg</td></tr>
        <tr><td>Hasil recovery masuk</td><td class="num">+{{ $kg($report['solvent']['recovered']) }} kg</td></tr>
        @if ($report['solvent']['adjustment'])
            <tr><td>Penyesuaian</td><td class="num">{{ $kg($report['solvent']['adjustment']) }} kg</td></tr>
        @endif
        <tr><td><strong>{{ $report['solvent']['closing_is_current'] ? 'Saldo saat ini' : 'Saldo akhir' }}</strong></td><td class="num"><strong>{{ $kg($report['solvent']['closing']) }} kg</strong></td></tr>
    </table>
</td>
<td class="col">
    <h2>Maintenance mesin</h2>
    @if ($report['maintenance']['count'])
        <table>
            <thead><tr><th>Mulai</th><th>Alasan</th><th class="num">Durasi</th></tr></thead>
            @foreach ($report['maintenance']['rows'] as $m)
                <tr><td>{{ Fmt::dateTime($m['start']) }}</td><td>{{ $m['reason'] }}</td><td class="num">{{ number_format($m['minutes_in_period'] / 60, 1, ',', '.') }} jam</td></tr>
            @endforeach
        </table>
        <div class="muted">Total downtime di periode: {{ number_format($report['maintenance']['total_minutes'] / 60, 1, ',', '.') }} jam.</div>
    @else
        <div class="empty">Tidak ada maintenance di periode ini.</div>
    @endif
</td>
</tr></table>

<div class="keep">
<h2>Perbandingan per shift</h2>
<table>
    <thead><tr><th>Shift</th><th class="num">Rangkaian</th><th class="num">Bahan masuk (kg)</th><th class="num">Recovery (kg)</th><th class="num">Residu estimasi (kg)</th><th class="num">Recovery rate</th></tr></thead>
    @foreach ($report['by_shift'] as $row)
        <tr>
            <td>{{ $row['shift'] }}</td><td class="num">{{ $row['chain_count'] }}</td><td class="num">{{ $kg($row['input']) }}</td>
            <td class="num">{{ $kg($row['recovery']) }}</td><td class="num">{{ $kg($row['residue']) }}</td>
            <td class="num {{ $row['chain_count'] && $row['recovery_rate'] < $report['target_recovery_rate'] ? 'bad' : '' }}">{{ $row['chain_count'] ? $pct($row['recovery_rate']) : '–' }}</td>
        </tr>
    @endforeach
</table>
</div>

@if ($report['breakdown'])
    @php $unitLabel = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$report['breakdown']['unit']]; @endphp
<div class="keep">
    <h2>Rincian per {{ strtolower($unitLabel) }}</h2>
    <table>
        <thead><tr><th>{{ $unitLabel }}</th><th class="num">Rangkaian</th><th class="num">Bahan masuk (kg)</th><th class="num">Recovery (kg)</th><th class="num">Residu estimasi (kg)</th><th class="num">Recovery rate</th></tr></thead>
        @foreach ($report['breakdown']['rows'] as $row)
            <tr>
                <td>{{ $report['breakdown']['unit'] === 'month' ? Fmt::month($row['start']) : ($row['start'] === $row['end'] ? Fmt::date($row['start']) : Fmt::date($row['start']) . ' – ' . Fmt::date($row['end'])) }}</td>
                <td class="num">{{ $row['chain_count'] }}</td><td class="num">{{ $kg($row['input']) }}</td><td class="num">{{ $kg($row['recovery']) }}</td>
                <td class="num">{{ $kg($row['residue']) }}</td>
                <td class="num {{ $row['chain_count'] && $row['recovery_rate'] < $report['target_recovery_rate'] ? 'bad' : '' }}">{{ $row['chain_count'] ? $pct($row['recovery_rate']) : '–' }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endif

<h2>Rangkaian di bawah target dan tinjauan supervisor</h2>
@if (count($report['below_target']))
    <table>
        <thead><tr><th>Rangkaian</th><th>Tanggal</th><th>Shift awal</th><th class="num">Recovery</th><th class="num">Target</th><th>Tinjauan supervisor</th></tr></thead>
        @foreach ($report['below_target'] as $c)
            <tr>
                <td>{{ $c['batch_number'] }}@if ($c['batch_count'] > 1)<br><span class="muted">+ {{ implode(', ', array_slice($c['batch_numbers'], 1)) }}</span>@endif</td>
                <td>{{ Fmt::date($c['date']) }}</td><td>{{ $c['shift'] }}</td>
                <td class="num bad">{{ $pct($c['recovery_rate']) }}</td><td class="num">{{ $pct($c['target_recovery_rate']) }}</td>
                <td>{!! $c['review'] ? e($c['review']['by']) . ': ' . e($c['review']['notes']) : '<span class="muted">Belum ditinjau</span>' !!}</td>
            </tr>
        @endforeach
    </table>
@else
    <div class="empty">Tidak ada rangkaian di bawah target di periode ini.</div>
@endif

@if (count($report['temp_abnormal']))
    <h2>Batch dengan suhu di luar normal</h2>
    <table>
        <thead><tr><th>No. batch</th><th>Tanggal</th><th>Shift</th><th class="num">Oil Temperature</th><th class="num">Vapour Temperature</th></tr></thead>
        @foreach ($report['temp_abnormal'] as $b)
            <tr><td>{{ $b['batch_number'] }}</td><td>{{ Fmt::date($b['date']) }}</td><td>{{ $b['shift'] }}</td><td class="num">{{ number_format($b['oil_temp'], 1, ',', '.') }} °C</td><td class="num">{{ number_format($b['vapour_temp'], 1, ',', '.') }} °C</td></tr>
        @endforeach
    </table>
@endif

<h2 class="page-break">Rincian rangkaian batch</h2>
@if (count($report['chains']))
    <table>
        <thead><tr><th>Rangkaian</th><th>Tanggal</th><th>Shift awal</th><th>Operator</th><th class="num">Tinta X</th><th class="num">Solvent</th><th class="num">Recovery</th><th class="num">Residu est.</th><th class="num">Recovery rate</th></tr></thead>
        @foreach ($report['chains'] as $c)
            <tr>
                <td>{{ $c['batch_number'] }}@if ($c['batch_count'] > 1)<br><span class="muted">+ {{ implode(', ', array_slice($c['batch_numbers'], 1)) }}</span>@endif</td>
                <td>{{ Fmt::date($c['date']) }}</td><td>{{ $c['shift'] }}</td><td>{{ $c['operator'] }}</td>
                <td class="num">{{ $kg($c['ink']) }}</td><td class="num">{{ $kg($c['solvent_used']) }}</td><td class="num">{{ $kg($c['recovery']) }}</td>
                <td class="num">{{ $c['is_complete'] ? $kg($c['residue']) : '–' }}</td>
                <td class="num {{ $c['is_below_target'] ? 'bad' : '' }}">{{ $c['is_complete'] ? $pct($c['recovery_rate']) : 'Belum selesai' }}</td>
            </tr>
        @endforeach
    </table>
    <div class="muted">Berat dalam kg.</div>
@else
    <div class="empty">Belum ada batch di periode ini.</div>
@endif

</body>
</html>
