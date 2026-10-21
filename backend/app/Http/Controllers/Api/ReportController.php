<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use App\Support\ReclaimerReport;
use App\Support\Reports\ReclaimerReportExcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** Pratinjau Laporan Reclaimer (FR-55). */
    public function reclaimer(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->report($request)->toArray()]);
    }

    /**
     * Tautan unduh bertanda tangan, berlaku 5 menit. Browser membuka tautan ini secara
     * biasa sehingga unduhan ditangani browser sendiri (lebih andal untuk berkas besar
     * daripada fetch + blob). Tautan terikat ke pengguna yang memintanya.
     */
    public function exportLink(Request $request): JsonResponse
    {
        $params = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
            'period' => ['nullable', 'in:' . implode(',', ReclaimerReport::PERIODS)],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], ['format.in' => 'Format ekspor harus xlsx atau pdf.']);

        $url = URL::temporarySignedRoute('reports.reclaimer.download', now()->addMinutes(5),
            array_filter($params) + ['user' => $request->user()->id], absolute: false);

        return response()->json(['status' => 'success', 'data' => ['url' => $url]]);
    }

    /** Unduhan lewat tautan bertanda tangan; hak akses dicek ulang saat diunduh. */
    public function downloadReclaimer(Request $request): Response|BinaryFileResponse
    {
        $user = User::find($request->query('user'));
        abort_unless($user && $user->is_active && $user->hasAccess('laporan'), 403, 'Anda tidak punya akses untuk mengunduh laporan ini.');
        $request->setUserResolver(fn () => $user);

        return $this->exportReclaimer($request);
    }

    /** Ekspor Laporan Reclaimer ke Excel atau PDF (FR-56). */
    public function exportReclaimer(Request $request): Response|BinaryFileResponse
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

        if ($format === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'laporan') . '.xlsx';
            (new ReclaimerReportExcel($data))->save($path);

            return response()->download($path, $report->filename('xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('images/logo-indofood-cbp.png')));

        $pdf = Pdf::loadView('reports.reclaimer', [
            'report' => $data,
            'logo' => $logo,
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        // Nomor halaman lewat canvas setelah render, tanpa menyalakan eksekusi PHP di template
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 30, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 7, [0.36, 0.40, 0.45]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $report->filename('pdf') . '"',
        ]);
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
