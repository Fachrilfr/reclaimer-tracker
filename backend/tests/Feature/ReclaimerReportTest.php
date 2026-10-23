<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReclaimerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Saldo awal solvent tercatat 1 Sep, sebelum periode yang diuji
        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->seed(DatabaseSeeder::class);
        $operator = User::where('nik', 'OP-1001')->first();
        $shift = Shift::orderBy('id')->value('id');

        // Saldo awal 1000 kg. Batch di 3 Okt dan 15 Okt, masing-masing disimpan pada harinya,
        // lalu solvent diambil dari saldo di hari yang sama.
        foreach ([['2026-10-03', 500, 200, 200], ['2026-10-15', 500, 100, 100]] as [$date, $ink, $recovery, $solvent]) {
            Carbon::setTestNow("{$date} 14:00:00");
            $this->app['auth']->forgetGuards();
            $this->actingAs($operator, 'sanctum')->postJson('/api/batches', [
                'date' => $date, 'shift_id' => $shift, 'is_continuation' => false, 'process_status' => 'completed',
                'ink_weight' => $ink, 'recovery_weight' => $recovery,
                'oil_temp' => $date === '2026-10-15' ? 170 : 140, 'vapour_temp' => 50,
            ])->assertCreated();
            $this->postJson('/api/solvent-usages', [
                'date' => $date, 'shift_id' => $shift, 'weight' => $solvent, 'purpose' => 'Pencucian mesin cetak',
            ])->assertCreated();
        }
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->as('SPV-2001');
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

    public function test_monthly_report_matches_dashboard(): void
    {
        $report = $this->getJson('/api/reports/reclaimer?period=month&date=2026-10-20')->assertOk()->json('data');
        $dashboard = $this->getJson('/api/dashboard?period=month&date=2026-10-20')->json('data');

        foreach (['input', 'recovery', 'residue', 'recovery_rate', 'chain_count'] as $key) {
            $this->assertSame($dashboard['totals'][$key], $report['summary'][$key], $key);
        }
        // Bahan masuk = Tinta X saja: (200 + 100) / (500 + 500) = 30%
        $this->assertEquals(30, $report['summary']['recovery_rate']);
        $this->assertSame('week', $report['breakdown']['unit']);
        $this->assertCount(2, $report['chains']);
        $this->assertCount(1, $report['below_target']);      // 100/500 = 20%
        $this->assertCount(1, $report['temp_abnormal']);

        // Saldo: 1000 + 200 - 200 + 100 - 100 = 1000. Pemakaian solvent dari catatan terpisah.
        $this->assertCount(2, $report['solvent_usages']);
        $this->assertSame('Pencucian mesin cetak', $report['solvent_usages'][0]['purpose']);
        $this->assertEquals(1000, $report['solvent']['opening']);
        $this->assertEquals(300, $report['solvent']['used']);
        $this->assertEquals(300, $report['solvent']['recovered']);
        $this->assertEquals(1000, $report['solvent']['closing']);
    }

    public function test_weekly_report_covers_monday_to_sunday(): void
    {
        $report = $this->getJson('/api/reports/reclaimer?period=week&date=2026-10-15')->assertOk()->json('data');

        $this->assertSame('2026-10-12', $report['start']);
        $this->assertSame('2026-10-18', $report['end']);
        $this->assertSame('day', $report['breakdown']['unit']);
        $this->assertCount(7, $report['breakdown']['rows']);
        $this->assertSame(1, $report['summary']['chain_count']);
        // Saldo awal minggu ini = saldo setelah batch 3 Okt
        $this->assertEquals(1000, $report['solvent']['opening']);
        $this->assertEquals(100, $report['solvent']['used']);
    }

    public function test_excel_export_contains_all_sheets_with_numbers(): void
    {
        $response = $this->get('/api/reports/reclaimer/export?format=xlsx&period=month&date=2026-10-20')->assertOk();
        $this->assertStringContainsString('Laporan-Reclaimer-Bulanan-2026-10.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($path, $response->getContent());
        $book = IOFactory::load($path);
        unlink($path);
        $this->assertSame(['Ringkasan', 'Per shift', 'Per Minggu', 'Rangkaian', 'Pemakaian solvent', 'Di bawah toleransi', 'Suhu tidak normal', 'Maintenance'], $book->getSheetNames());
        $summary = $book->getSheetByName('Ringkasan');
        $this->assertSame('Tinta X masuk (kg)', $summary->getCell('A9')->getValue());
        $this->assertEquals(1000, $summary->getCell('B9')->getValue());
        $this->assertSame('Persentase solvent', $summary->getCell('A12')->getValue());
        $this->assertEquals(30, $summary->getCell('B12')->getValue());
        $usages = $book->getSheetByName('Pemakaian solvent');
        $this->assertEquals(200, $usages->getCell('C6')->getValue());
        $this->assertEquals(300, $usages->getCell('C8')->getValue()); // baris total
        $this->assertSame('BR-20261015-001', $book->getSheetByName('Di bawah toleransi')->getCell('A6')->getValue());

        $this->assertDatabaseHas('audit_logs', ['action' => 'export', 'entity_type' => 'reports']);
    }

    public function test_pdf_export_is_a_pdf(): void
    {
        $response = $this->get('/api/reports/reclaimer/export?format=pdf&period=year&date=2026-10-20')->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Laporan-Reclaimer-Tahunan-2026.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_app_receives_file_as_json(): void
    {
        // Jalur aplikasi web: berkas dikirim dalam JSON supaya tidak dicegat pengelola unduhan
        $data = $this->getJson('/api/reports/reclaimer/export?format=pdf&period=month&date=2026-10-20')
            ->assertOk()->json('data');

        $this->assertSame('Laporan-Reclaimer-Bulanan-2026-10.pdf', $data['filename']);
        $this->assertSame('application/pdf', $data['content_type']);
        $this->assertStringStartsWith('%PDF', base64_decode($data['content']));
    }

    public function test_access_and_validation(): void
    {
        $this->as('OP-1001')->getJson('/api/reports/reclaimer')->assertForbidden();
        foreach (['HSE-3001', 'MGT-4001', 'ADM-5001'] as $nik) {
            $this->as($nik)->getJson('/api/reports/reclaimer')->assertOk();
        }
        $this->getJson('/api/reports/reclaimer?period=jam')->assertUnprocessable();
        $this->get('/api/reports/reclaimer/export?format=docx', ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
