<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ReclaimerReport;
use App\Support\Reports\ReclaimerReportExcel;
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
        return response()->json(['status' => 'success', 'data' => $this->report($request)->toArray()]);
    }

    /**
     * Ekspor Laporan Reclaimer ke Excel atau PDF (FR-56).
     *
     * Aplikasi web meminta dengan Accept: application/json dan menerima isi berkas dalam
     * base64, lalu menyimpannya sendiri. Pengelola unduhan seperti IDM mencegat respons
     * PDF biasa di browser (termasuk fetch) dan membatalkannya, tetapi tidak menyentuh JSON.
     */
    public function exportReclaimer(Request $request): Response
    {
        $format = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
        ], ['format.in' => 'Format ekspor harus xlsx atau pdf.'])['format'];

        $report = $this->report($request);
        $data = $report->toArray();

        // Jejak audit ekspor laporan (FR-57)
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export',
            'entity_type' => 'reports',
            'entity_id' => null,
            'new_values' => ['report' => 'reclaimer', 'format' => $format, 'period' => $data['period'], 'start' => $data['start'], 'end' => $data['end']],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        $content = $format === 'xlsx' ? $this->excel($data, $request->user()->name) : $this->pdf($data, $request->user()->name);
        $filename = $report->filename($format);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => [
                'filename' => $filename,
                'content_type' => self::MIME[$format],
                'content' => base64_encode($content),
            ]]);
        }

        return response($content, 200, [
            'Content-Type' => self::MIME[$format],
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    private function excel(array $data, string $generatedBy): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laporan');
        try {
            (new ReclaimerReportExcel($data, $generatedBy))->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function pdf(array $data, string $generatedBy): string
    {
        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('images/logo-indofood-cbp.png')));

        $pdf = Pdf::loadView('reports.reclaimer', [
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

    private function report(Request $request): ReclaimerReport
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:' . implode(',', ReclaimerReport::PERIODS)],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'period.in' => 'Periode harus harian, mingguan, bulanan, atau tahunan.',
            'date.date_format' => 'Tanggal tidak valid.',
            'date.before_or_equal' => 'Tanggal tidak boleh setelah hari ini.',
        ]);

        return new ReclaimerReport(
            $data['period'] ?? 'month',
            isset($data['date']) ? Carbon::parse($data['date']) : now(),
        );
    }
}
