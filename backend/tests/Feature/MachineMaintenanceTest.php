<?php

namespace Tests\Feature;

use App\Models\B3Pallet;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MachineMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-10-20 09:15:00');
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

    private function batch()
    {
        return $this->postJson('/api/batches', [
            'date' => '2026-10-20', 'shift_id' => Shift::orderBy('id')->value('id'), 'is_continuation' => false,
            'process_status' => 'completed', 'ink_weight' => 500,
            'recovery_weight' => 180, 'oil_temp' => 140, 'vapour_temp' => 50,
        ]);
    }

    public function test_operator_reports_maintenance_and_recipients_are_notified(): void
    {
        $this->as('OP-1001')->postJson('/api/machine/maintenance', [
            'reason' => 'Pompa vakum bocor',
            'expected_end_at' => '2026-10-20 15:00:00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.maintenance_reason', 'Pompa vakum bocor')
            ->assertJsonPath('data.maintenance_reported_by', 'Operator Aziz');

        foreach (['HSE-3001', 'SPV-2001', 'MGT-4001', 'ADM-5001'] as $nik) {
            $this->as($nik)->getJson('/api/notifications')->assertOk()
                ->assertJsonPath('data.enabled', true)
                ->assertJsonPath('data.unread_count', 1)
                ->assertJsonPath('data.items.0.kind', 'maintenance_started')
                ->assertJsonPath('data.items.0.title', 'Mesin IRAC-01 sedang maintenance')
                ->assertJsonPath('data.machine.status', 'maintenance');
        }

        // Operator tidak menerima pemberitahuan, tapi tetap melihat status mesin untuk banner
        $this->as('OP-1001')->getJson('/api/notifications')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.machine.status', 'maintenance');

        $this->assertDatabaseHas('machine_maintenance_logs', ['action' => 'start', 'reason' => 'Pompa vakum bocor']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance_start']);
    }

    public function test_batch_is_blocked_during_maintenance(): void
    {
        $this->as('SPV-2001')->postJson('/api/machine/maintenance', ['reason' => 'Ganti seal'])->assertOk();

        $this->as('OP-1001')->batch()
            ->assertUnprocessable()
            ->assertJsonPath('errors.machine.0', 'Mesin IRAC-01 sedang maintenance. Batch tidak bisa disimpan sampai mesin kembali beroperasi.');
        $this->assertDatabaseCount('reclaimer_batches', 0);
        $this->assertDatabaseCount('solvent_ledger', 1);

        $this->getJson('/api/batches/create')->assertJsonPath('data.machine.status', 'maintenance');

        Carbon::setTestNow('2026-10-20 15:45:00');
        $this->postJson('/api/machine/operating', ['note' => 'Seal pompa diganti'])->assertOk()
            ->assertJsonPath('data.status', 'operating');
        $this->batch()->assertCreated();

        // Penerima mendapat dua pemberitahuan; yang terbaru berisi durasi
        $this->as('HSE-3001')->getJson('/api/notifications')
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonPath('data.items.0.kind', 'maintenance_ended')
            ->assertJsonPath('data.items.0.duration_minutes', 390);

        // Supervisor yang melapor tidak diberi tahu tentang laporannya sendiri, tapi
        // menerima pemberitahuan saat operator menyelesaikan
        $this->as('SPV-2001')->getJson('/api/notifications')->assertJsonPath('data.unread_count', 1);

        $this->as('OP-1001')->getJson('/api/machine')->assertOk()
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.reason', 'Ganti seal')
            ->assertJsonPath('data.history.0.end_note', 'Seal pompa diganti')
            ->assertJsonPath('data.history.0.started_by', 'Supervisor Reclaimer')
            ->assertJsonPath('data.history.0.ended_by', 'Operator Aziz')
            ->assertJsonPath('data.history.0.duration_minutes', 390);
    }

    public function test_status_rules_and_validation(): void
    {
        $this->as('OP-1001')->postJson('/api/machine/maintenance', [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson('/api/machine/operating', ['note' => 'x'])->assertUnprocessable()
            ->assertJsonPath('errors.note.0', 'Mesin IRAC-01 tidak sedang maintenance.');

        $this->postJson('/api/machine/maintenance', ['reason' => 'Bocor'])->assertOk();
        $this->postJson('/api/machine/maintenance', ['reason' => 'Lagi'])->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_access_follows_matrix(): void
    {
        $this->as('HSE-3001')->getJson('/api/machine')->assertOk();
        $this->postJson('/api/machine/maintenance', ['reason' => 'x'])->assertForbidden();
        $this->as('MGT-4001')->postJson('/api/machine/maintenance', ['reason' => 'x'])->assertForbidden();
        $this->as('ADM-5001')->getJson('/api/machine')->assertOk();
    }

    public function test_notifications_can_be_marked_read(): void
    {
        $this->as('OP-1001')->postJson('/api/machine/maintenance', ['reason' => 'Bocor'])->assertOk();
        $this->postJson('/api/machine/operating', ['note' => 'Selesai'])->assertOk();

        $id = $this->as('HSE-3001')->getJson('/api/notifications')->json('data.items.1.id');
        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.1.read', true);

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0);

        // Pemberitahuan milik orang lain tidak bisa disentuh
        $this->as('MGT-4001');
        $this->postJson("/api/notifications/{$id}/read")->assertNotFound();
    }

    public function test_late_pallet_shows_maintenance_as_reason(): void
    {
        // Pallet mulai 10 Okt, batas kirim ke TPS 17 Okt
        $pallet = B3Pallet::create([
            'pallet_number' => 'PLT-202610-001', 'start_date' => '2026-10-10', 'max_storage_days' => 90,
            'expiry_date' => '2027-01-08', 'tps_due_date' => '2026-10-17', 'status' => 'filling',
        ]);

        Carbon::setTestNow('2026-10-15 08:00:00');
        $this->as('OP-1001')->postJson('/api/machine/maintenance', ['reason' => 'Kondensor tersumbat'])->assertOk();
        Carbon::setTestNow('2026-10-18 13:00:00');
        $this->postJson('/api/machine/operating', ['note' => 'Kondensor dibersihkan'])->assertOk();

        // Belum dikirim dan lewat batas: keterangan penyebab ikut tampil
        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->getJson('/api/pallets')->assertOk()
            ->assertJsonPath('data.filling.tps.level', 'kritis')
            ->assertJsonPath('data.filling.tps.delay.type', 'machine_maintenance')
            ->assertJsonPath('data.filling.tps.delay.reason', 'Kondensor tersumbat');

        // Setelah dikirim terlambat, keterangan tetap tersimpan di riwayat
        $pallet->update(['drum_count' => 3, 'status' => 'full', 'full_date' => '2026-10-19', 'sent_to_tps_at' => '2026-10-19']);
        $this->getJson('/api/pallets')
            ->assertJsonPath('data.recent.0.tps.late_days', 2)
            ->assertJsonPath('data.recent.0.tps.delay.end_note', 'Kondensor dibersihkan');

        // Pallet yang tepat waktu tidak diberi keterangan
        $pallet->update(['sent_to_tps_at' => '2026-10-16']);
        $this->getJson('/api/pallets')->assertJsonPath('data.recent.0.tps.delay', null);
    }
}
