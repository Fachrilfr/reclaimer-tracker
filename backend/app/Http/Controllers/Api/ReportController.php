<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\B3Report;
use App\Support\ReclaimerReport;
use App\Support\ReportPeriod;
use App\Support\Reports\B3ReportExcel;
use App\Support\Reports\ReclaimerReportExcel;
use App\Support\Reports\ReportWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    private const MIME = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    /** Pratinjau Laporan Reclaimer (FR-55). */
    public function reclaimer(Request $request): JsonResponse
    {
        [$period, $anchor] = $this->period($request);

        return response()->json(['status' => 'success', 'data' => (new ReclaimerReport($period, $anchor))->toArray()]);
    }

    /** Ekspor Laporan Reclaimer ke Excel atau PDF (FR-56). */
    public function exportReclaimer(Request $request): Response
    {
        [$period, $anchor] = $this->period($request);
        $report = new ReclaimerReport($period, $anchor);

        return $this->export($request, 'reclaimer', $report->toArray(), fn ($ext) => $report->filename($ext), ReclaimerReportExcel::class, 'reports.reclaimer');
    }

    /** Pratinjau Laporan Limbah B3 (HSE). */
    public function b3(Request $request): JsonResponse
    {
        [$period, $anchor] = $this->period($request);

        return response()->json(['status' => 'success', 'data' => (new B3Report($period, $anchor))->toArray()]);
    }

    public function exportB3(Request $request): Response
    {
        [$period, $anchor] = $this->period($request);
        $report = new B3Report($period, $anchor);

        return $this->export($request, 'limbah_b3', $report->toArray(), fn ($ext) => $report->filename($ext), B3ReportExcel::class, 'reports.b3');
    }

    /**
     * Aplikasi web meminta dengan Accept: application/json dan menerima isi berkas dalam
     * base64, lalu menyimpannya sendiri. Pengelola unduhan seperti IDM mencegat respons
     * PDF biasa di browser (termasuk fetch) dan membatalkannya, tetapi tidak menyentuh JSON.
     *
     * @param  class-string<ReportWorkbook>  $workbook
     */
    private function export(Request $request, string $name, array $data, callable $filename, string $workbook, string $view): Response
    {
        $format = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
        ], ['format.in' => 'Format ekspor harus xlsx atau pdf.'])['format'];

        // Jejak audit ekspor laporan (FR-57)
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export',
            'entity_type' => 'reports',
            'entity_id' => null,
            'new_values' => ['report' => $name, 'format' => $format, 'period' => $data['period'], 'start' => $data['start'], 'end' => $data['end']],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        $generatedBy = $request->user()->name;
        $content = $format === 'xlsx' ? $this->excel(new $workbook($data, $generatedBy)) : $this->pdf($view, $data, $generatedBy);
        $file = $filename($format);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => [
                'filename' => $file,
                'content_type' => self::MIME[$format],
                'content' => base64_encode($content),
            ]]);
        }

        return response($content, 200, [
            'Content-Type' => self::MIME[$format],
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    private function excel(ReportWorkbook $workbook): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laporan');
        try {
            $workbook->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function pdf(string $view, array $data, string $generatedBy): string
    {
        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('images/logo-indofood-cbp.png')));

        $pdf = Pdf::loadView($view, [
            'report' => $data,
            'logo' => $logo,
            'generatedBy' => $generatedBy,
        ])->setPaper('a4', 'landscape')
            // Hanya huruf yang dipakai yang disematkan: berkas turun dari ~1 MB
            ->setOption('isFontSubsettingEnabled', true);

        // Nomor halaman lewat canvas setelah render, tanpa menyalakan eksekusi PHP di template
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 30, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 7, [0.36, 0.40, 0.45]);

        return $dompdf->output();
    }

    /** @return array{0: string, 1: Carbon} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:' . implode(',', ReportPeriod::PERIODS)],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'period.in' => 'Periode harus harian, mingguan, bulanan, atau tahunan.',
            'date.date_format' => 'Tanggal tidak valid.',
            'date.before_or_equal' => 'Tanggal tidak boleh setelah hari ini.',
        ]);

        return [$data['period'] ?? 'month', isset($data['date']) ? Carbon::parse($data['date']) : now()];
    }
}
