<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Laporan Limbah B3: berat dari surat jalan per tanggal serah terima, kepatuhan masa
 * simpan pallet yang diserahkan, dan stok pada akhir periode.
 */
class B3ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
        [$first, $second] = Vendor::orderBy('id')->pluck('id')->all();

        // A dan B diserahkan 5 Okt (A lewat batas: sampai di TPS 1 Jul + 90 hari = 29 Sep),
        // C 15 Okt, D masih di TPS, E masih diisi di area reclaimer
        $a = $this->pallet('2026-06-25', '2026-07-01');
        $b = $this->pallet('2026-08-01', '2026-08-07');
        $c = $this->pallet('2026-08-10', '2026-08-16');
        $this->pallet('2026-09-01', '2026-09-04');
        $this->pallet('2026-10-18', null);

        $this->as('HSE-3001');
        $this->suratJalan('SJ-001', $first, '2026-10-05', [$a, $b], 600);
        $this->suratJalan('SJ-002', $second, '2026-10-15', [$c], 300);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as(string $nik): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::where('nik', $nik)->first(), 'sanctum');
    }

    /** Operator menekan "mulai diisi" pada $start dan "penuh" pada $full (terkirim ke TPS). */
    private function pallet(string $start, ?string $full): int
    {
        $this->as('OP-1001');
        Carbon::setTestNow("{$start} 08:00:00");
        $id = $this->postJson('/api/pallets')->assertCreated()->json('data.id');
        if ($full) {
            Carbon::setTestNow("{$full} 15:00:00");
            $this->postJson("/api/pallets/{$id}/full")->assertOk();
        }
        Carbon::setTestNow('2026-10-20 10:00:00');

        return $id;
    }

    private function suratJalan(string $number, int $vendor, string $date, array $pallets, float $weight): void
    {
        $this->postJson('/api/surat-jalan', [
            'surat_jalan_number' => $number, 'vendor_id' => $vendor, 'handover_date' => $date,
            'pallet_ids' => $pallets, 'weight_kg' => $weight,
            'file' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ])->assertCreated();
    }

    public function test_monthly_report_sums_vendor_weight_by_handover_date(): void
    {
        $report = $this->getJson('/api/reports/b3?period=month&date=2026-10-20')->assertOk()->json('data');

        $this->assertSame(2, $report['summary']['surat_jalan_count']);
        $this->assertSame(3, $report['summary']['pallet_count']);
        $this->assertSame(9, $report['summary']['drum_count']);
        $this->assertEquals(900, $report['summary']['weight_kg']);
        $this->assertSame(1, $report['summary']['over_limit_count']);
        $this->assertSame(96, $report['summary']['longest_days_stored']);

        $this->assertEquals(600, $report['by_vendor'][0]['weight_kg']);
        $this->assertSame('week', $report['breakdown']['unit']);
        $this->assertEquals(600, $report['breakdown']['rows'][1]['weight_kg']); // 5-11 Okt
        $this->assertEquals(300, $report['breakdown']['rows'][2]['weight_kg']); // 12-18 Okt

        // Lewat batas di urutan pertama
        $this->assertSame(6, $report['compliance'][0]['over_days']);
        $this->assertSame(0, $report['compliance'][1]['over_days']);

        // Stok hari ini: D di TPS, E di area reclaimer
        $this->assertTrue($report['stock']['is_current']);
        $this->assertSame(['tps', 'reclaimer'], array_column($report['stock']['rows'], 'location'));
        // Pallet yang masih diisi tidak dihitung per drum lagi
        $this->assertNull($report['stock']['rows'][1]['drum_count']);
    }

    public function test_stock_is_taken_at_the_end_of_a_past_period(): void
    {
        $report = $this->getJson('/api/reports/b3?period=month&date=2026-09-15')->assertOk()->json('data');

        $this->assertSame(0, $report['summary']['surat_jalan_count']);
        $this->assertFalse($report['stock']['is_current']);
        $this->assertSame('2026-09-30', $report['stock']['as_of']);
        // Per 30 Sep: A, B, C, D masih di TPS; E belum dimulai. A lewat batas 1 hari.
        $this->assertCount(4, $report['stock']['rows']);
        $this->assertSame(-1, $report['stock']['rows'][0]['days_remaining']);
        $this->assertSame('kritis', $report['stock']['rows'][0]['level']);
    }

    public function test_excel_and_pdf_exports(): void
    {
        $response = $this->get('/api/reports/b3/export?format=xlsx&period=month&date=2026-10-20')->assertOk();
        $this->assertStringContainsString('Laporan-Limbah-B3-Bulanan-2026-10.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($path, $response->getContent());
        $book = IOFactory::load($path);
        unlink($path);
        $this->assertSame(['Ringkasan', 'Per vendor', 'Per Minggu', 'Surat jalan', 'Masa simpan', 'Stok akhir periode', 'Maintenance'], $book->getSheetNames());
        $this->assertSame('Berat timbangan vendor (kg)', $book->getSheetByName('Ringkasan')->getCell('A9')->getValue());
        $this->assertEquals(900, $book->getSheetByName('Ringkasan')->getCell('B9')->getValue());
        $this->assertSame('Lewat batas 6 hari', $book->getSheetByName('Masa simpan')->getCell('H6')->getValue());

        $pdf = $this->getJson('/api/reports/b3/export?format=pdf&period=year&date=2026-10-20')->assertOk()->json('data');
        $this->assertSame('Laporan-Limbah-B3-Tahunan-2026.pdf', $pdf['filename']);
        $this->assertStringStartsWith('%PDF', base64_decode($pdf['content']));
        $this->assertDatabaseHas('audit_logs', ['action' => 'export', 'entity_type' => 'reports']);
    }

    public function test_document_preview_and_maintenance_section(): void
    {
        $report = $this->getJson('/api/reports/b3?period=month&date=2026-10-20')->assertOk()->json('data');
        $this->assertSame(['count', 'total_minutes', 'rows'], array_keys($report['maintenance']));

        // Pratinjau dokumen: isi sama dengan PDF, dibaca di aplikasi tanpa mengunduh
        $html = $this->getJson('/api/reports/b3/document?period=month&date=2026-10-20')->assertOk()->json('data.html');
        $this->assertStringContainsString('Laporan Limbah B3 Bulanan', $html);
        $this->assertStringContainsString('Maintenance mesin', $html);
        $this->assertStringContainsString('SJ-001', $html);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'export', 'entity_type' => 'reports']);

        $this->as('SPV-2001')->getJson('/api/reports/b3/document')->assertForbidden();
        $this->getJson('/api/reports/reclaimer/document?period=month&date=2026-10-20')->assertOk()
            ->assertJsonPath('data.html', fn ($html) => str_contains($html, 'Laporan Reclaimer Bulanan'));
    }

    public function test_only_hse_management_and_admin(): void
    {
        foreach (['HSE-3001', 'MGT-4001', 'ADM-5001'] as $nik) {
            $this->as($nik)->getJson('/api/reports/b3')->assertOk();
        }
        foreach (['SPV-2001', 'OP-1001'] as $nik) {
            $this->as($nik)->getJson('/api/reports/b3')->assertForbidden();
        }
    }
}
