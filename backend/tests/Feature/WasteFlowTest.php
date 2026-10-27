<?php

namespace Tests\Feature;

use App\Actions\SendStorageReminders;
use App\Models\B3Pallet;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur limbah B3: operator mengisi pallet sampai penuh (terkirim ke TPS), HSE
 * memantau di Limbah B3, lalu menyerahkannya ke vendor lewat surat jalan.
 */
class WasteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 10:00:00');
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
    private function fullPallet(string $start, ?string $full): int
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

    private function suratJalan(array $overrides = []): array
    {
        return $overrides + [
            'surat_jalan_number' => 'SJ/IDF/2026/10/001',
            'vendor_id' => Vendor::orderBy('id')->value('id'),
            'handover_date' => '2026-10-20',
            'weight_kg' => 612.5,
            'vendor_officer_name' => 'Petugas PPLI',
            'notes' => null,
            'file' => UploadedFile::fake()->create('surat-jalan.pdf', 120, 'application/pdf'),
        ];
    }

    public function test_hse_sees_operator_pallets_by_location(): void
    {
        $atTps = $this->fullPallet('2026-07-26', '2026-08-01');
        $this->fullPallet('2026-10-18', null);

        $response = $this->as('HSE-3001')->getJson('/api/b3/pallets')->assertOk();
        $response->assertJsonPath('meta.counts.tps', 1)
            ->assertJsonPath('meta.counts.reclaimer', 1)
            ->assertJsonPath('meta.counts.diserahkan', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $atTps)
            ->assertJsonPath('data.0.location', 'tps')
            ->assertJsonPath('data.0.drum_count', 3)
            ->assertJsonPath('data.0.tps.sent_at', '2026-08-01')
            // Masa simpan 90 hari sejak sampai di TPS 1 Agustus: 30 Okt, sisa 10 hari
            ->assertJsonPath('data.0.storage.days_remaining', 10)
            ->assertJsonPath('data.0.storage.level', 'mendesak')
            ->assertJsonPath('meta.needs_action', 1);

        // Pallet di area reclaimer belum terhitung masa simpannya
        $this->getJson('/api/b3/pallets?lokasi=reclaimer')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location', 'reclaimer')
            ->assertJsonPath('data.0.storage.level', 'reclaimer')
            ->assertJsonPath('data.0.storage.days_remaining', null)
            ->assertJsonPath('data.0.expiry_date', null);
    }

    public function test_reminders_follow_levels_and_critical_repeats_daily(): void
    {
        $id = $this->fullPallet('2026-07-26', '2026-08-01');
        // Pallet yang masih diisi sejak lama tidak pernah dapat pengingat masa simpan
        $this->fullPallet('2026-06-01', null);
        $reminders = app(SendStorageReminders::class);

        // Sisa 10 hari: mendesak, sekali saja
        $this->assertSame(1, $reminders->handle(Carbon::parse('2026-10-20 07:00')));
        $this->assertSame(0, $reminders->handle(Carbon::parse('2026-10-20 07:00')));
        $this->assertSame(0, $reminders->handle(Carbon::parse('2026-10-21 07:00')));

        // Sisa 7 hari ke bawah: kritis, dikirim ulang tiap hari
        $this->assertSame(1, $reminders->handle(Carbon::parse('2026-10-23 07:00')));
        $this->assertSame(1, $reminders->handle(Carbon::parse('2026-10-24 07:00')));

        $hse = User::where('nik', 'HSE-3001')->first();
        $this->assertSame(3, $hse->notifications()->count());
        $this->assertSame("/limbah-b3?lokasi=semua&sorot=pallet-{$id}", $hse->notifications()->first()->data['url']);
        $this->assertSame(0, User::where('nik', 'SPV-2001')->first()->notifications()->count());

        $this->as('HSE-3001')->getJson('/api/b3/reminders')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.level', 'kritis')
            ->assertJsonPath('data.0.days_remaining', 6);

        // Setelah diserahkan lewat surat jalan, pengingat berhenti
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$id]]))->assertCreated();
        $this->assertSame(0, $reminders->handle(Carbon::parse('2026-10-25 07:00')));
    }

    public function test_surat_jalan_hands_over_tps_pallets_and_closes_them(): void
    {
        $first = $this->fullPallet('2026-08-01', '2026-08-07');
        $second = $this->fullPallet('2026-08-10', '2026-08-16');

        $this->as('HSE-3001')->getJson('/api/surat-jalan/create')->assertOk()
            ->assertJsonCount(2, 'data.pallets')
            ->assertJsonPath('data.min_pallets', 1)
            ->assertJsonCount(2, 'data.vendors');

        $data = $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$first, $second]]))
            ->assertCreated()
            ->assertJsonPath('data.number', 'SJ/IDF/2026/10/001')
            ->assertJsonPath('data.total_pallets', 2)
            ->assertJsonPath('data.weight_kg', 612.5)
            ->assertJsonPath('data.file_name', 'surat-jalan.pdf')
            ->json('data');

        $this->assertSame('transported', B3Pallet::find($first)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity_type' => 'surat_jalan']);

        // Pallet pindah ke "diserahkan" dengan nomor surat jalannya
        $this->getJson('/api/b3/pallets?lokasi=diserahkan')->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.storage.level', 'diserahkan')
            ->assertJsonPath('data.0.surat_jalan.number', 'SJ/IDF/2026/10/001');
        $this->getJson('/api/surat-jalan/create')->assertJsonCount(0, 'data.pallets');

        // Pallet yang sudah diserahkan tidak bisa diserahkan lagi
        $this->postJson('/api/surat-jalan', $this->suratJalan(['surat_jalan_number' => 'SJ-002', 'pallet_ids' => [$first]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['pallet_ids']);

        // Arsip: cari per pallet, vendor, bulan; bukti bisa diunduh
        $this->getJson('/api/surat-jalan?q=' . B3Pallet::find($second)->pallet_number)->assertJsonCount(1, 'data');
        $this->getJson('/api/surat-jalan?q=Wastec')->assertJsonCount(0, 'data');
        $this->getJson('/api/surat-jalan?bulan=2026-10')->assertJsonCount(1, 'data');
        $this->getJson('/api/surat-jalan?bulan=2026-09')->assertJsonCount(0, 'data');
        $this->getJson("/api/surat-jalan/{$data['id']}/file")->assertOk()
            ->assertJsonPath('data.filename', 'surat-jalan.pdf')
            ->assertJsonPath('data.content_type', 'application/pdf');
    }

    public function test_surat_jalan_rules(): void
    {
        $atTps = $this->fullPallet('2026-08-01', '2026-08-07');
        $this->fullPallet('2026-10-18', null);
        $filling = B3Pallet::where('status', 'filling')->value('id');

        $this->as('HSE-3001');
        // Bukti, berat, dan pallet wajib
        $this->postJson('/api/surat-jalan', $this->suratJalan(['file' => null, 'weight_kg' => null, 'pallet_ids' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors(['file', 'weight_kg', 'pallet_ids']);
        // Bukti harus PDF, JPG, atau PNG
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$atTps], 'file' => UploadedFile::fake()->create('bukti.docx', 10)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);
        // Pallet yang masih diisi di reclaimer belum bisa diserahkan
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$filling]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['pallet_ids']);
        // Serah terima tidak boleh sebelum pallet sampai di TPS
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$atTps], 'handover_date' => '2026-08-06']))
            ->assertUnprocessable()->assertJsonValidationErrors(['handover_date']);

        $this->assertDatabaseCount('surat_jalan', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('surat-jalan'));

        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$atTps]]))->assertCreated();
        // Nomor surat jalan unik
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [$atTps]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['surat_jalan_number']);
    }

    public function test_access_follows_matrix(): void
    {
        $this->as('SPV-2001')->getJson('/api/b3/pallets')->assertOk();
        $this->getJson('/api/surat-jalan')->assertOk();
        $this->postJson('/api/surat-jalan', $this->suratJalan(['pallet_ids' => [1]]))->assertForbidden();

        $this->as('MGT-4001')->getJson('/api/surat-jalan')->assertOk();
        $this->as('OP-1001')->getJson('/api/b3/pallets')->assertForbidden();
        $this->getJson('/api/surat-jalan')->assertForbidden();
    }
}
