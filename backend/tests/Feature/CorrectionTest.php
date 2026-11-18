<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\B3Pallet;
use App\Models\Machine;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Koreksi data oleh Supervisor dan Super Admin: alasan wajib, nilai lama tercatat di
 * riwayat koreksi, baris diberi tanda dikoreksi, dan saldo solvent disesuaikan lewat
 * baris penyesuaian. Saldo awal dari seeder: 1000 kg.
 */
class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    private int $shift1;

    private int $shift2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 16:30:00');
        [$this->shift1, $this->shift2] = Shift::orderBy('id')->pluck('id')->all();
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

    private function batch(array $data): int
    {
        return $this->as('10000001')->postJson('/api/batches', $data + [
            'date' => '2026-10-20',
            'shift_id' => $this->shift1,
            'is_continuation' => false,
            'parent_batch_id' => null,
            'process_status' => 'completed',
            'ink_weight' => 500,
            'recovery_weight' => 0,
            'oil_temp' => 140,
            'vapour_temp' => 50,
            'notes' => null,
        ])->assertCreated()->json('data.id');
    }

    /** Isian koreksi input: nilai sekarang ditimpa $changes. */
    private function batchForm(int $id, array $changes): array
    {
        $b = ReclaimerBatch::findOrFail($id);

        return $changes + [
            'date' => $b->date->toDateString(),
            'shift_id' => $b->shift_id,
            'regu' => $b->regu,
            'process_status' => $b->process_status,
            'recovery_weight' => $b->recovery_weight,
            'oil_temp' => $b->oil_temp,
            'vapour_temp' => $b->vapour_temp,
            'notes' => $b->notes,
            'reason' => 'Salah ketik di lapangan',
        ];
    }

    public function test_only_supervisor_and_super_admin_can_correct(): void
    {
        foreach (['20000001', '50000001'] as $nik) {
            $this->as($nik)->getJson('/api/koreksi/input')->assertOk();
            $this->getJson('/api/koreksi/riwayat')->assertOk();
        }
        foreach (['10000001', '30000001', '40000001'] as $nik) {
            $this->as($nik)->getJson('/api/koreksi/input')->assertForbidden();
            $this->getJson('/api/koreksi/riwayat')->assertForbidden();
        }
    }

    public function test_recovery_typo_is_corrected_with_a_solvent_adjustment(): void
    {
        // Seharusnya 150 kg, tertulis 450 kg: saldo 1000 + 450, persentase 90%
        $id = $this->batch(['recovery_weight' => 450]);
        $this->assertEquals(1450, SolventLedger::currentBalance());

        $this->as('20000001')->putJson("/api/koreksi/input/{$id}", $this->batchForm($id, ['reason' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->putJson("/api/koreksi/input/{$id}", $this->batchForm($id, []))
            ->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Tidak ada data yang diubah. Ubah isian yang salah dulu, lalu simpan.');
        $this->putJson("/api/koreksi/input/{$id}", $this->batchForm($id, ['recovery_weight' => 600]))
            ->assertUnprocessable()->assertJsonValidationErrors(['recovery_weight']);

        $this->putJson("/api/koreksi/input/{$id}", $this->batchForm($id, ['recovery_weight' => 150, 'reason' => 'Salah ketik, seharusnya 150 kg']))
            ->assertOk()
            ->assertJsonPath('message', 'Input BR-20261020-001 dikoreksi. Saldo solvent disesuaikan −300,00 kg, menjadi 1.150,00 kg.');

        $batch = ReclaimerBatch::find($id);
        $this->assertEquals(150, $batch->recovery_weight);
        $this->assertEquals(350, $batch->residue_estimated_weight);
        $this->assertEquals(30, $batch->chain_recovery_rate);
        // Sekarang di bawah toleransi 40% (snapshot saat selesai)
        $this->assertTrue($batch->is_recovery_low);
        $this->assertSame('Salah ketik, seharusnya 150 kg', $batch->correction_reason);
        $this->assertSame(User::where('nik', '20000001')->value('id'), $batch->corrected_by);

        // Mutasi lama tetap, koreksi masuk sebagai penyesuaian
        $ledger = SolventLedger::orderBy('id')->get();
        $this->assertEquals(450, $ledger[1]->amount);
        $this->assertSame('adjustment', $ledger[2]->type);
        $this->assertEquals(-300, $ledger[2]->amount);
        $this->assertSame($id, $ledger[2]->batch_id);
        $this->assertEquals(1150, SolventLedger::currentBalance());

        $audit = AuditLog::where('action', 'correct')->firstOrFail();
        $this->assertEquals(450, $audit->old_values['recovery_weight']);
        $this->assertEquals(150, $audit->new_values['recovery_weight']);
        $this->assertSame('Salah ketik, seharusnya 150 kg', $audit->new_values['reason']);

        // Tanda dikoreksi ikut tampil di riwayat input dan riwayat koreksi
        $this->getJson('/api/batches')->assertOk()->assertJsonPath('data.0.correction.reason', 'Salah ketik, seharusnya 150 kg');
        $this->getJson('/api/koreksi/riwayat')->assertOk()
            ->assertJsonPath('data.0.label', 'BR-20261020-001')
            ->assertJsonPath('data.0.entity', 'Input reclaimer')
            ->assertJsonPath('data.0.changes.0.field', 'recovery_weight');
    }

    public function test_status_and_delete_follow_the_process_chain(): void
    {
        $root = $this->batch(['recovery_weight' => 100, 'process_status' => 'in_progress']);
        $next = $this->batch(['shift_id' => $this->shift2, 'is_continuation' => true, 'parent_batch_id' => $root, 'ink_weight' => 0, 'recovery_weight' => 150]);
        $this->assertNotNull(ReclaimerBatch::find($root)->chain_completed_at);

        $this->as('50000001')->putJson("/api/koreksi/input/{$root}", $this->batchForm($root, ['process_status' => 'completed']))
            ->assertUnprocessable()->assertJsonValidationErrors(['process_status']);
        $this->deleteJson("/api/koreksi/input/{$root}", ['reason' => 'Dobel'])
            ->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Input ini masih punya input lanjutan. Hapus dulu input terakhir proses ini (BR-20261020-002).');
        $this->deleteJson("/api/koreksi/input/{$next}", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        // Input lanjutan yang menutup proses dihapus: proses terbuka lagi, hasil solventnya keluar dari saldo
        $this->deleteJson("/api/koreksi/input/{$next}", ['reason' => 'Lanjutan tidak pernah ada'])->assertOk()
            ->assertJsonPath('message', 'Input BR-20261020-002 dihapus. Proses BR-20261020-001 dibuka lagi dan menunggu input lanjutan. Saldo solvent disesuaikan −150,00 kg, menjadi 1.100,00 kg.');
        $this->assertSoftDeleted('reclaimer_batches', ['id' => $next]);
        $this->assertNull(ReclaimerBatch::find($root)->chain_completed_at);
        $this->as('10000001')->getJson('/api/batches/create')->assertJsonPath('data.open_chains.0.root_id', $root);

        // Status input terakhir diubah menjadi selesai: proses ditutup dan dinilai
        $this->as('20000001')->putJson("/api/koreksi/input/{$root}", $this->batchForm($root, ['process_status' => 'completed']))->assertOk()
            ->assertJsonPath('message', 'Input BR-20261020-001 dikoreksi. Proses BR-20261020-001 sekarang selesai.');
        $root_ = ReclaimerBatch::find($root);
        $this->assertNotNull($root_->chain_completed_at);
        $this->assertEquals(20, $root_->chain_recovery_rate);

        $this->deleteJson("/api/koreksi/input/{$root}", ['reason' => 'Input percobaan'])->assertOk();
        $this->assertEquals(1000, SolventLedger::currentBalance());
        $this->getJson('/api/koreksi/input')->assertJsonCount(0, 'data');
    }

    public function test_date_must_stay_in_chain_order(): void
    {
        $root = $this->batch(['date' => '2026-10-19', 'process_status' => 'in_progress']);
        $this->batch(['is_continuation' => true, 'parent_batch_id' => $root, 'ink_weight' => 0, 'recovery_weight' => 100]);

        $this->as('20000001')->putJson("/api/koreksi/input/{$root}", $this->batchForm($root, ['date' => '2026-10-20']))->assertOk();
        $this->putJson("/api/koreksi/input/{$root}", $this->batchForm($root, ['date' => '2026-10-20', 'oil_temp' => 200]))->assertOk();
        $this->assertTrue(ReclaimerBatch::find($root)->is_temp_abnormal);
    }

    public function test_solvent_usage_correction_and_delete(): void
    {
        // Seharusnya 90 kg, tertulis 900 kg
        $id = $this->as('10000001')->postJson('/api/solvent-usages', [
            'date' => '2026-10-20', 'shift_id' => $this->shift1, 'weight' => 900, 'purpose' => 'Pencucian mesin cetak',
        ])->assertCreated()->json('data.id');
        $form = ['date' => '2026-10-20', 'shift_id' => $this->shift1, 'purpose' => 'Pencucian mesin cetak', 'reason' => 'Salah ketik'];

        $this->as('20000001')->putJson("/api/koreksi/solvent/{$id}", $form + ['weight' => 90])->assertOk()
            ->assertJsonPath('message', 'Pemakaian solvent 20 Okt 2026 (900,00 kg) dikoreksi. Saldo solvent disesuaikan +810,00 kg, menjadi 910,00 kg.');
        $usage = SolventUsage::find($id);
        $this->assertEquals(90, $usage->weight);
        $this->assertEquals(100, $usage->balance_after); // saldo saat itu tetap

        // Menambah pemakaian melebihi saldo ditolak, sama seperti mencatat pemakaian baru
        $this->putJson("/api/koreksi/solvent/{$id}", $form + ['weight' => 1500])
            ->assertUnprocessable()->assertJsonValidationErrors(['weight']);

        $this->deleteJson("/api/koreksi/solvent/{$id}", ['reason' => 'Tidak jadi diambil'])->assertOk();
        $this->assertSoftDeleted('solvent_usages', ['id' => $id]);
        $this->assertEquals(1000, SolventLedger::currentBalance());
        $this->as('10000001')->getJson('/api/solvent-usages')->assertJsonCount(0, 'data');
    }

    public function test_pallet_correction_and_delete_rules(): void
    {
        $this->as('10000001');
        Carbon::setTestNow('2026-10-10 08:00:00');
        $a = $this->postJson('/api/pallets')->assertCreated()->json('data.id');
        Carbon::setTestNow('2026-10-12 15:00:00');
        $this->postJson("/api/pallets/{$a}/full")->assertOk();
        Carbon::setTestNow('2026-10-20 16:30:00');

        // Tombol penuh telat ditekan: sebenarnya penuh 11 Okt, isinya hanya 2 drum
        $form = ['status' => 'full', 'start_date' => '2026-10-10', 'full_date' => '2026-10-11', 'drum_count' => 2, 'notes' => null, 'reason' => 'Tombol penuh telat ditekan'];
        $this->as('20000001')->putJson("/api/koreksi/pallet/{$a}", $form)->assertOk()
            ->assertJsonPath('message', 'Pallet PLT-202610-001 dikoreksi. Batas simpan sekarang 9 Jan 2027.');
        $pallet = B3Pallet::find($a);
        $this->assertSame('2026-10-11', $pallet->sent_to_tps_at->toDateString());
        $this->assertSame('2027-01-09', $pallet->expiry_date->toDateString());
        $this->assertSame('15:00', $pallet->full_at->format('H:i'));
        $this->assertSame(2, (int) $pallet->drum_count);

        $this->putJson("/api/koreksi/pallet/{$a}", ['full_date' => '2026-10-09'] + $form)
            ->assertUnprocessable()->assertJsonValidationErrors(['full_date']);

        // Pallet B sedang diisi: A tidak bisa dikembalikan menjadi masih diisi
        $b = $this->as('10000001')->postJson('/api/pallets')->assertCreated()->json('data.id');
        $this->as('20000001')->putJson("/api/koreksi/pallet/{$a}", ['status' => 'filling'] + $form)
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->deleteJson("/api/koreksi/pallet/{$b}", ['reason' => 'Salah tekan mulai isi'])->assertOk();
        $this->assertSoftDeleted('b3_pallets', ['id' => $b]);

        $this->putJson("/api/koreksi/pallet/{$a}", ['status' => 'filling'] + $form)->assertOk();
        $pallet = B3Pallet::find($a);
        $this->assertSame('filling', $pallet->status);
        $this->assertNull($pallet->sent_to_tps_at);
        $this->assertNull($pallet->expiry_date);
    }

    public function test_transported_pallet_cannot_be_deleted(): void
    {
        $this->as('10000001');
        $a = $this->postJson('/api/pallets')->assertCreated()->json('data.id');
        $this->postJson("/api/pallets/{$a}/full")->assertOk();
        $this->suratJalan('SJ-001', [$a]);

        $this->as('20000001')->deleteJson("/api/koreksi/pallet/{$a}", ['reason' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Pallet ini sudah diangkut dengan surat jalan SJ-001. Keluarkan pallet dari surat jalan itu dulu lewat koreksi surat jalan.');
    }

    private function suratJalan(string $number, array $pallets): int
    {
        return $this->as('30000001')->postJson('/api/surat-jalan', [
            'surat_jalan_number' => $number, 'vendor_id' => Vendor::orderBy('id')->value('id'), 'handover_date' => '2026-10-20',
            'pallet_ids' => $pallets, 'weight_kg' => 600, 'vendor_officer_name' => 'Petugas vendor',
        ])->assertCreated()->json('data.id');
    }

    public function test_surat_jalan_correction_and_delete(): void
    {
        $this->as('10000001');
        $a = $this->postJson('/api/pallets')->assertCreated()->json('data.id');
        $this->postJson("/api/pallets/{$a}/full")->assertOk();
        $b = $this->postJson('/api/pallets')->assertCreated()->json('data.id');
        $this->postJson("/api/pallets/{$b}/full")->assertOk();
        $first = $this->suratJalan('SJ-001', [$a]);
        [$vendorA, $vendorB] = Vendor::orderBy('id')->pluck('id')->all();

        $form = ['surat_jalan_number' => 'SJ-001A', 'vendor_id' => $vendorB, 'handover_date' => '2026-10-20', 'weight_kg' => 60,
            'vendor_officer_name' => 'Petugas vendor', 'pallet_ids' => [$a, $b], 'reason' => 'Nomor dan berat salah ketik'];
        $this->as('50000001')->postJson("/api/koreksi/surat-jalan/{$first}", $form)->assertOk()
            ->assertJsonPath('message', 'Surat jalan SJ-001A dikoreksi.');
        $this->assertSame('transported', B3Pallet::find($b)->status);

        // Pallet A dikeluarkan: kembali ke TPS
        $this->postJson("/api/koreksi/surat-jalan/{$first}", ['pallet_ids' => [$b]] + $form)->assertOk()
            ->assertJsonPath('message', 'Surat jalan SJ-001A dikoreksi. 1 pallet kembali ke TPS.');
        $this->assertSame('tps', B3Pallet::find($a)->location());

        $audit = AuditLog::where('action', 'correct')->where('entity_type', 'surat_jalan')->orderBy('id')->firstOrFail();
        $this->assertSame('SJ-001', $audit->old_values['surat_jalan_number']);
        $this->assertEquals(600, $audit->old_values['weight_kg']);
        $this->assertSame('PLT-202610-001, PLT-202610-002', $audit->new_values['pallets']);

        // Nomor yang sudah dipakai surat jalan lain ditolak
        $second = $this->suratJalan('SJ-002', [$a]);
        $this->as('20000001')->postJson("/api/koreksi/surat-jalan/{$second}", ['pallet_ids' => [$a]] + $form)
            ->assertUnprocessable()->assertJsonValidationErrors(['surat_jalan_number']);

        // Dihapus: pallet kembali ke TPS dan nomornya bisa dipakai lagi
        $this->deleteJson("/api/koreksi/surat-jalan/{$first}", ['reason' => 'Surat jalan dobel'])->assertOk()
            ->assertJsonPath('message', 'Surat jalan SJ-001A dihapus. 1 pallet kembali ke TPS.');
        $this->assertSame('full', B3Pallet::find($b)->status);
        $this->suratJalan('SJ-001A', [$b]);
        $this->as('20000001')->getJson('/api/koreksi/riwayat?jenis=surat_jalan')->assertOk()->assertJsonPath('data.0.action', 'delete');
    }

    public function test_maintenance_correction_and_delete(): void
    {
        $this->as('20000001');
        Carbon::setTestNow('2026-10-20 08:00:00');
        $this->postJson('/api/machine/maintenance', ['reason' => 'Pompa bocor'])->assertOk();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->postJson('/api/machine/operating', ['note' => 'Seal diganti'])->assertOk();
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->postJson('/api/machine/maintenance', ['reason' => 'Vakum lemah'])->assertOk();
        Carbon::setTestNow('2026-10-20 16:30:00');

        $windows = $this->getJson('/api/koreksi/maintenance')->assertOk()->json('data');
        [$open, $closed] = $windows;
        $this->assertSame('2026-10-20T12:00', $open['start_local']);
        $this->assertNull($open['end_local']);

        $form = ['start' => '2026-10-20T07:30', 'end' => '2026-10-20T09:45', 'maintenance_reason' => 'Pompa vakum bocor', 'end_note' => 'Seal diganti', 'reason' => 'Jam lapor telat'];
        $this->putJson("/api/koreksi/maintenance/{$closed['id']}", ['end' => '2026-10-20T12:30'] + $form)
            ->assertUnprocessable()->assertJsonValidationErrors(['end']);
        $this->putJson("/api/koreksi/maintenance/{$closed['id']}", $form)->assertOk();
        $this->getJson('/api/machine')->assertJsonPath('data.history.1.duration_minutes', 135)
            ->assertJsonPath('data.history.1.reason', 'Pompa vakum bocor')
            ->assertJsonPath('data.history.1.correction.reason', 'Jam lapor telat');

        // Maintenance yang masih berlangsung ikut mengubah status mesin
        $this->putJson("/api/koreksi/maintenance/{$open['id']}", ['start' => '2026-10-20T11:00', 'maintenance_reason' => 'Vakum lemah', 'reason' => 'Mulai jam 11'])->assertOk();
        $this->assertSame('11:00', Machine::main()->maintenance_started_at->format('H:i'));

        $this->deleteJson("/api/koreksi/maintenance/{$open['id']}", ['reason' => 'Laporan salah'])->assertOk()
            ->assertJsonPath('message', 'Maintenance 20 Okt 2026, 11.00 dihapus. Mesin IRAC-01 kembali berstatus beroperasi.');
        $this->assertFalse(Machine::main()->isUnderMaintenance());
        $this->getJson('/api/koreksi/riwayat?jenis=machine_maintenance_logs')->assertOk()
            ->assertJsonPath('data.1.changes.0.field', 'start')
            ->assertJsonPath('data.0.reason', 'Laporan salah');
    }

    public function test_super_admin_can_turn_correction_off_for_supervisor(): void
    {
        $levels = $this->as('50000001')->getJson('/api/role-access')->assertOk()->json('data.roles.supervisor.levels');
        $this->assertSame('kelola', $levels['koreksi']);

        $this->putJson('/api/role-access/supervisor', ['levels' => ['koreksi' => null] + $levels])->assertOk();
        $this->as('20000001')->getJson('/api/koreksi/input')->assertForbidden();
    }
}
