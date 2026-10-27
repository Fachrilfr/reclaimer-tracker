{{-- Gaya bersama PDF laporan (dompdf): kop, kaki, tabel, kotak angka --}}
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
    .warn { color: #9A6700; font-weight: bold; }
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
@if (! empty($screen))
{{-- Mode layar untuk pratinjau dokumen di aplikasi: satu halaman panjang seperti kertas --}}
<style>
    html { background: #EEF1F5; }
    body { display: flex; flex-direction: column; width: 269mm; min-width: 860px; margin: 6mm auto; padding: 10mm 12mm; background: #FFFFFF; box-sizing: border-box; box-shadow: 0 1px 3px rgba(20, 26, 33, 0.12); }
    header { position: relative; top: auto; margin-bottom: 6mm; }
    footer { position: static; order: 99; margin-top: 8mm; padding-top: 2mm; border-top: 1px solid #DDE3EA; }
    .page-break { page-break-before: auto; margin-top: 8mm; }
    /* Cetak dari pratinjau: kertas A4 mendatar, tanpa bayangan */
    @page { size: A4 landscape; margin: 12mm; }
    @media print {
        html { background: #FFFFFF; }
        body { width: auto; min-width: 0; margin: 0; padding: 0; box-shadow: none; }
        .page-break { break-before: page; margin-top: 0; }
    }
</style>
@endif
