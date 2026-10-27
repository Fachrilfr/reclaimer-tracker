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
@include('reports.partials.style')
</head>
<body>
<header>
    <img src="{{ $logo }}" alt="Indofood CBP">
    <div class="title">Material Balance &amp; B3 Tracker<br>Laporan Reclaimer {{ $report['period_label'] }} · {{ $period }}</div>
</header>
<footer>Dibuat {{ Fmt::dateTime($report['generated_at']) }} WIB oleh {{ $generatedBy }} · Angka dari rangkaian batch yang sudah selesai, dihitung pada tanggal dan shift batch awalnya.</footer>

<div>
<h1>Laporan Reclaimer {{ $report['period_label'] }}</h1>
<div class="meta">Periode {{ $period }} · {{ $s['chain_count'] }} rangkaian selesai ({{ $s['batch_count'] }} batch)@if ($s['open_chain_count']) · {{ $s['open_chain_count'] }} rangkaian belum selesai tidak dihitung @endif</div>

<table class="kpi"><tr>
    <td><div class="box"><div class="label">Tinta X masuk</div><div class="value">{{ $kg($s['input']) }} kg</div><div class="muted">Bahan masuk ke mesin reclaimer</div></div></td>
    <td><div class="box"><div class="label">Hasil solvent</div><div class="value">{{ $kg($s['recovery']) }} kg</div><div class="muted">Residu estimasi {{ $kg($s['residue']) }} kg</div></div></td>
    <td><div class="box"><div class="label">Persentase solvent</div><div class="value {{ $s['chain_count'] && $s['recovery_rate'] < $report['target_recovery_rate'] ? 'bad' : '' }}">{{ $s['chain_count'] ? $pct($s['recovery_rate']) : '–' }}</div><div class="muted">Toleransi {{ $pct($report['target_recovery_rate']) }} · {{ $s['below_target_count'] }} rangkaian di bawah toleransi</div></div></td>
    <td><div class="box"><div class="label">{{ $report['solvent']['closing_is_current'] ? 'Saldo solvent saat ini' : 'Saldo solvent akhir' }}</div><div class="value">{{ $kg($report['solvent']['closing']) }} kg</div><div class="muted">Saldo awal {{ $kg($report['solvent']['opening']) }} kg</div></div></td>
</tr></table>
</div>

<table class="two"><tr>
<td class="col">
    <h2>Saldo solvent</h2>
    <div class="muted">Bertambah dari hasil solvent tiap batch (termasuk rangkaian yang belum selesai), berkurang saat solvent diambil.</div>
    <table>
        <tr><td>Saldo awal</td><td class="num">{{ $kg($report['solvent']['opening']) }} kg</td></tr>
        <tr><td>Hasil solvent masuk</td><td class="num">+{{ $kg($report['solvent']['recovered']) }} kg</td></tr>
        <tr><td>Solvent dipakai</td><td class="num">−{{ $kg($report['solvent']['used']) }} kg</td></tr>
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
    <thead><tr><th>Shift</th><th class="num">Rangkaian</th><th class="num">Tinta X masuk (kg)</th><th class="num">Hasil solvent (kg)</th><th class="num">Residu estimasi (kg)</th><th class="num">Persentase solvent</th></tr></thead>
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
        <thead><tr><th>{{ $unitLabel }}</th><th class="num">Rangkaian</th><th class="num">Tinta X masuk (kg)</th><th class="num">Hasil solvent (kg)</th><th class="num">Residu estimasi (kg)</th><th class="num">Persentase solvent</th></tr></thead>
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

<div>
<h2>Rangkaian di bawah toleransi dan tinjauan supervisor</h2>
@if (count($report['below_target']))
    <table>
        <thead><tr><th>Rangkaian</th><th>Tanggal</th><th>Shift awal</th><th class="num">Solvent</th><th class="num">Toleransi</th><th>Tinjauan supervisor</th></tr></thead>
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
    <div class="empty">Tidak ada rangkaian di bawah toleransi di periode ini.</div>
@endif
</div>

@if (count($report['temp_abnormal']))
<div>
    <h2>Batch dengan suhu di luar normal</h2>
    <table>
        <thead><tr><th>No. batch</th><th>Tanggal</th><th>Shift</th><th class="num">Suhu oli</th><th class="num">Suhu uap</th></tr></thead>
        @foreach ($report['temp_abnormal'] as $b)
            <tr><td>{{ $b['batch_number'] }}</td><td>{{ Fmt::date($b['date']) }}</td><td>{{ $b['shift'] }}</td><td class="num">{{ number_format($b['oil_temp'], 1, ',', '.') }} °C</td><td class="num">{{ number_format($b['vapour_temp'], 1, ',', '.') }} °C</td></tr>
        @endforeach
    </table>
</div>
@endif

@if (count($report['solvent_usages']))
<div class="keep">
    <h2>Pemakaian solvent</h2>
    <div class="muted">Solvent yang diambil dari saldo hasil solvent. Tidak masuk ke mesin dan tidak ikut dihitung sebagai bahan masuk.</div>
    <table>
        <thead><tr><th>Tanggal</th><th>Shift</th><th class="num">Berat diambil</th><th>Keperluan</th><th>Dicatat oleh</th><th class="num">Saldo sesudah</th></tr></thead>
        @foreach ($report['solvent_usages'] as $u)
            <tr><td>{{ Fmt::date($u['date']) }}</td><td>{{ $u['shift'] }}</td><td class="num">{{ $kg($u['weight']) }} kg</td><td>{{ $u['purpose'] }}@if ($u['notes'])<br><span class="muted">{{ $u['notes'] }}</span>@endif</td><td>{{ $u['used_by'] }}</td><td class="num">{{ $kg($u['balance_after']) }} kg</td></tr>
        @endforeach
    </table>
</div>
@endif

<div class="page-break">
<h2>Rincian rangkaian batch</h2>
@if (count($report['chains']))
    <table>
        <thead><tr><th>Rangkaian</th><th>Tanggal</th><th>Shift awal</th><th>Operator</th><th class="num">Tinta X</th><th class="num">Solvent</th><th class="num">Residu est.</th><th class="num">Persentase solvent</th></tr></thead>
        @foreach ($report['chains'] as $c)
            <tr>
                <td>{{ $c['batch_number'] }}@if ($c['batch_count'] > 1)<br><span class="muted">+ {{ implode(', ', array_slice($c['batch_numbers'], 1)) }}</span>@endif</td>
                <td>{{ Fmt::date($c['date']) }}</td><td>{{ $c['shift'] }}</td><td>{{ $c['operator'] }}</td>
                <td class="num">{{ $kg($c['input']) }}</td><td class="num">{{ $kg($c['recovery']) }}</td>
                <td class="num">{{ $c['is_complete'] ? $kg($c['residue']) : '–' }}</td>
                <td class="num {{ $c['is_below_target'] ? 'bad' : '' }}">{{ $c['is_complete'] ? $pct($c['recovery_rate']) : 'Belum selesai' }}</td>
            </tr>
        @endforeach
    </table>
    <div class="muted">Berat dalam kg.</div>
@else
    <div class="empty">Belum ada batch di periode ini.</div>
@endif
</div>

</body>
</html>
