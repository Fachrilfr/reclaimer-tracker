@php
    use App\Support\Reports\B3ReportExcel as Fmt;
    $kg = fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '.');
    $s = $report['summary'];
    $stock = $report['stock'];
    $period = Fmt::date($report['start']) . ($report['start'] === $report['end'] ? '' : ' – ' . Fmt::date($report['end']));
    $stockLabel = $stock['is_current'] ? 'hari ini' : 'per ' . Fmt::date($stock['as_of']);
    $levelClass = ['aman' => '', 'perhatian' => 'warn', 'mendesak' => 'bad', 'kritis' => 'bad'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Limbah B3 {{ $report['period_label'] }}</title>
@include('reports.partials.style')
</head>
<body>
<header>
    <img src="{{ $logo }}" alt="Indofood CBP">
    <div class="title">Material Balance &amp; B3 Tracker<br>Laporan Limbah B3 {{ $report['period_label'] }} · {{ $period }}</div>
</header>
<footer>Dibuat {{ Fmt::dateTime($report['generated_at']) }} WIB oleh {{ $generatedBy }} · Berat resmi dari timbangan vendor, dicatat per surat jalan. Periode mengikuti tanggal serah terima.</footer>

<div data-bagian="Ringkasan">
<h1>Laporan Limbah B3 {{ $report['period_label'] }}</h1>
<div class="meta">Periode {{ $period }} · {{ $s['surat_jalan_count'] }} surat jalan · batas masa simpan {{ $report['max_storage_days'] }} hari sejak pallet mulai terisi</div>

<table class="kpi"><tr>
    <td><div class="box"><div class="label">Berat diserahkan (timbangan vendor)</div><div class="value">{{ $kg($s['weight_kg']) }} kg</div><div class="muted">{{ $s['surat_jalan_count'] }} surat jalan</div></div></td>
    <td><div class="box"><div class="label">Pallet diserahkan</div><div class="value">{{ $s['pallet_count'] }}</div><div class="muted">{{ $s['drum_count'] }} drum</div></div></td>
    <td><div class="box"><div class="label">Diserahkan lewat batas simpan</div><div class="value {{ $s['over_limit_count'] ? 'bad' : '' }}">{{ $s['over_limit_count'] }} pallet</div><div class="muted">{{ $s['longest_days_stored'] !== null ? 'Terlama ' . $s['longest_days_stored'] . ' hari' : 'Belum ada penyerahan' }}</div></div></td>
    <td><div class="box"><div class="label">Stok {{ $stockLabel }}</div><div class="value">{{ count($stock['rows']) }} pallet</div><div class="muted">{{ collect($stock['rows'])->where('location', 'tps')->count() }} di TPS · {{ collect($stock['rows'])->where('location', 'reclaimer')->count() }} di area reclaimer</div></div></td>
</tr></table>
</div>

<table class="two" data-bagian="Per vendor dan per periode"><tr>
<td class="col">
    <h2>Per vendor</h2>
    @if (count($report['by_vendor']))
        <table>
            <thead><tr><th>Vendor</th><th class="num">Surat jalan</th><th class="num">Pallet</th><th class="num">Berat (kg)</th></tr></thead>
            @foreach ($report['by_vendor'] as $v)
                <tr><td>{{ $v['vendor'] }}</td><td class="num">{{ $v['surat_jalan_count'] }}</td><td class="num">{{ $v['pallet_count'] }}</td><td class="num">{{ $kg($v['weight_kg']) }}</td></tr>
            @endforeach
        </table>
    @else
        <div class="empty">Tidak ada limbah B3 yang diserahkan ke vendor di periode ini.</div>
    @endif
</td>
<td class="col">
    @if ($report['breakdown'])
        @php $unitLabel = ['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'][$report['breakdown']['unit']]; @endphp
        <h2>Per {{ strtolower($unitLabel) }}</h2>
        <table>
            <thead><tr><th>{{ $unitLabel }}</th><th class="num">Surat jalan</th><th class="num">Pallet</th><th class="num">Berat (kg)</th></tr></thead>
            @foreach ($report['breakdown']['rows'] as $row)
                <tr>
                    <td>{{ $report['breakdown']['unit'] === 'month' ? Fmt::month($row['start']) : ($row['start'] === $row['end'] ? Fmt::date($row['start']) : Fmt::date($row['start']) . ' – ' . Fmt::date($row['end'])) }}</td>
                    <td class="num">{{ $row['surat_jalan_count'] }}</td><td class="num">{{ $row['pallet_count'] }}</td><td class="num">{{ $row['weight_kg'] ? $kg($row['weight_kg']) : '–' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</td>
</tr></table>

<div class="keep" data-bagian="Surat jalan">
<h2>Surat jalan</h2>
@if (count($report['surat_jalan']))
    <table>
        <thead><tr><th>No. surat jalan</th><th>Serah terima</th><th>Vendor</th><th>Pallet</th><th class="num">Berat (kg)</th><th>Petugas vendor</th><th>Dicatat oleh</th></tr></thead>
        @foreach ($report['surat_jalan'] as $sj)
            <tr>
                <td style="white-space: nowrap">{{ $sj['number'] }}@if ($sj['notes'])<br><span class="muted">{{ $sj['notes'] }}</span>@endif</td>
                <td>{{ Fmt::date($sj['handover_date']) }}</td>
                <td>{{ $sj['vendor'] }}</td>
                <td>{{ implode(', ', array_column($sj['pallets'], 'pallet_number')) }}</td>
                <td class="num">{{ $kg($sj['weight_kg']) }}</td>
                <td>{{ $sj['vendor_officer_name'] ?? '–' }}</td>
                <td>{{ $sj['created_by'] }}</td>
            </tr>
        @endforeach
    </table>
@else
    <div class="empty">Belum ada surat jalan di periode ini.</div>
@endif
</div>

<div class="keep" data-bagian="Kepatuhan masa simpan">
<h2>Kepatuhan masa simpan</h2>
<div class="muted">Lama simpan pallet yang diserahkan, dari mulai terisi sampai serah terima.</div>
@if (count($report['compliance']))
    <table>
        <thead><tr><th>Pallet</th><th>Surat jalan</th><th>Mulai terisi</th><th>Serah terima</th><th>Batas simpan</th><th class="num">Lama simpan</th><th>Status</th></tr></thead>
        @foreach ($report['compliance'] as $c)
            <tr>
                <td>{{ $c['pallet_number'] }}</td><td>{{ $c['surat_jalan_number'] }}</td>
                <td>{{ Fmt::date($c['start_date']) }}</td><td>{{ Fmt::date($c['handover_date']) }}</td><td>{{ Fmt::date($c['expiry_date']) }}</td>
                <td class="num">{{ $c['days_stored'] }} hari</td>
                <td class="{{ $c['over_days'] ? 'bad' : '' }}">{{ $c['over_days'] ? 'Lewat batas ' . $c['over_days'] . ' hari' : 'Dalam batas' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <div class="empty">Tidak ada pallet yang diserahkan di periode ini.</div>
@endif
</div>

<div class="keep" data-bagian="Stok">
<h2>Stok {{ $stockLabel }}</h2>
<div class="muted">Pallet yang belum diserahkan ke vendor, dengan sisa masa simpan saat itu.</div>
@if (count($stock['rows']))
    <table>
        <thead><tr><th>Pallet</th><th>Lokasi</th><th class="num">Drum</th><th>Mulai terisi</th><th>Sampai di TPS</th><th>Batas simpan</th><th class="num">Sisa masa simpan</th><th>Status</th></tr></thead>
        @foreach ($stock['rows'] as $p)
            <tr>
                <td>{{ $p['pallet_number'] }}</td><td>{{ Fmt::LOCATION[$p['location']] }}</td><td class="num">{{ $p['drum_count'] ?? 'Sedang diisi' }}</td>
                <td>{{ Fmt::date($p['start_date']) }}</td><td>{{ $p['sent_to_tps_at'] ? Fmt::date($p['sent_to_tps_at']) : '–' }}</td><td>{{ Fmt::date($p['expiry_date']) }}</td>
                <td class="num">{{ $p['days_remaining'] < 0 ? 'lewat ' . -$p['days_remaining'] . ' hari' : $p['days_remaining'] . ' hari' }}</td>
                <td class="{{ $levelClass[$p['level']] }}">{{ Fmt::LEVEL[$p['level']] }}</td>
            </tr>
        @endforeach
    </table>
@else
    <div class="empty">Tidak ada pallet yang tersimpan.</div>
@endif
</div>

<div class="keep" data-bagian="Maintenance mesin">
<h2>Maintenance mesin</h2>
<div class="muted">Selama maintenance, pengiriman residu ke TPS ikut terhenti; batas kirim tetap berjalan.</div>
@if ($report['maintenance']['count'])
    <table>
        <thead><tr><th>Mulai</th><th>Selesai</th><th>Alasan</th><th>Catatan perbaikan</th><th class="num">Durasi di periode</th></tr></thead>
        @foreach ($report['maintenance']['rows'] as $m)
            <tr>
                <td>{{ Fmt::dateTime($m['start']) }}</td><td>{{ $m['end'] ? Fmt::dateTime($m['end']) : 'Masih berlangsung' }}</td>
                <td>{{ $m['reason'] }}</td><td>{{ $m['end_note'] ?? '–' }}</td>
                <td class="num">{{ number_format($m['minutes_in_period'] / 60, 1, ',', '.') }} jam</td>
            </tr>
        @endforeach
    </table>
    <div class="muted">Total downtime di periode: {{ number_format($report['maintenance']['total_minutes'] / 60, 1, ',', '.') }} jam.</div>
@else
    <div class="empty">Mesin tidak menjalani maintenance di periode ini.</div>
@endif
</div>

</body>
</html>
