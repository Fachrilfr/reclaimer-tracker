<?php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Parameter dan Data master: siapa yang boleh mengubah, aturan urutan
 * jenjang pengingat, dan jejak perubahan di audit log.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $nik): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(User::where('nik', $nik)->first(), 'sanctum');
    }

    private function values(array $overrides = []): array
    {
        $values = collect($this->as('HSE-3001')->getJson('/api/parameters')->assertOk()->json('data'))
            ->flatMap(fn ($group) => collect($group['items'])->mapWithKeys(fn ($item) => [$item['key'] => $item['value']]))
            ->all();

        return ['values' => $overrides + $values];
    }

    public function test_parameters_are_grouped_and_hide_unused_keys(): void
    {
        $keys = collect($this->as('SPV-2001')->getJson('/api/parameters')->assertOk()->json('data'))->flatMap(fn ($g) => array_column($g['items'], 'key'));

        $this->assertContains('target_recovery_rate', $keys);
        $this->assertNotContains('daily_reminder_hour', $keys);
        $this->assertNotContains('max_vendor_delivery_weeks', $keys);
        // Isi pallet tetap 3 drum dan batas kirim ke TPS dihapus
        $this->assertNotContains('drums_per_pallet', $keys);
        $this->assertNotContains('tps_delivery_days', $keys);
    }

    public function test_hse_updates_parameters_with_audit_trail(): void
    {
        $this->putJson('/api/parameters', $this->values(['target_recovery_rate' => 42.5, 'b3_max_storage_days' => 100]))
            ->assertOk()->assertJsonPath('message', '2 parameter tersimpan.');

        $this->assertSame(42.5, Parameter::number('target_recovery_rate'));
        $this->assertSame(100.0, Parameter::number('b3_max_storage_days'));
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'parameters', 'action' => 'update']);
        $this->assertSame(User::where('nik', 'HSE-3001')->value('id'), Parameter::where('param_key', 'b3_max_storage_days')->value('updated_by'));
    }

    public function test_reminder_levels_must_stay_in_order(): void
    {
        $this->putJson('/api/parameters', $this->values(['b3_reminder_urgent_days' => 30]))
            ->assertUnprocessable()->assertJsonValidationErrors(['values.b3_reminder_urgent_days']);
        $this->putJson('/api/parameters', $this->values(['b3_max_storage_days' => 90.5]))
            ->assertUnprocessable()->assertJsonValidationErrors(['values.b3_max_storage_days']);
        $this->assertSame(14.0, Parameter::number('b3_reminder_urgent_days'));
    }

    public function test_only_managers_can_change_parameters(): void
    {
        $body = $this->values();
        $this->as('OP-1001')->getJson('/api/parameters')->assertForbidden();
        $this->as('MGT-4001')->putJson('/api/parameters', $body)->assertForbidden();
        $this->as('SPV-2001')->putJson('/api/parameters', $body)->assertOk()->assertJsonPath('message', 'Tidak ada perubahan.');
    }

    public function test_master_data_shifts_and_vendors(): void
    {
        $this->as('HSE-3001')->getJson('/api/master')->assertOk()
            ->assertJsonCount(3, 'data.shifts')->assertJsonMissingPath('data.materials');

        $this->postJson('/api/master/vendors', ['name' => 'PT Baru Lestari', 'pic_name' => 'Bu Sari'])->assertOk();
        $vendor = Vendor::where('name', 'PT Baru Lestari')->first();
        $this->putJson("/api/master/vendors/{$vendor->id}", ['name' => 'PT Baru Lestari', 'is_active' => false])->assertOk();
        $this->assertFalse($vendor->fresh()->is_active);
        // Vendor nonaktif tidak muncul di form surat jalan
        $this->getJson('/api/surat-jalan/create')->assertOk()
            ->assertJsonMissing(['name' => 'PT Baru Lestari']);

        $this->postJson('/api/master/shifts', ['code' => 'shift-4', 'name' => 'Shift 4', 'start_time' => '07:00', 'end_time' => '07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['end_time']);
        $this->postJson('/api/master/shifts', ['code' => 'shift-4', 'name' => 'Shift 4', 'start_time' => '06:00', 'end_time' => '18:00'])->assertOk();
        $this->assertDatabaseHas('shifts', ['code' => 'SHIFT-4', 'start_time' => '06:00:00']);

        // Minimal satu shift tetap aktif
        Shift::where('code', '!=', 'SHIFT-1')->update(['is_active' => false]);
        $shift = Shift::where('code', 'SHIFT-1')->first();
        $this->putJson("/api/master/shifts/{$shift->id}", ['code' => 'SHIFT-1', 'name' => $shift->name, 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);

        // Shift yang sudah dipakai tidak bisa dihapus; shift baru yang belum dipakai bisa
        $this->deleteJson("/api/master/shifts/{$shift->id}")->assertUnprocessable()->assertJsonValidationErrors(['shift']);
        $this->deleteJson('/api/master/shifts/' . Shift::where('code', 'SHIFT-4')->value('id'))->assertOk();
        $this->assertDatabaseMissing('shifts', ['code' => 'SHIFT-4']);
        // Vendor dihapus lunak
        $this->deleteJson("/api/master/vendors/{$vendor->id}")->assertOk();
        $this->assertSoftDeleted('vendors', ['id' => $vendor->id]);

        $this->as('SPV-2001')->getJson('/api/master')->assertOk();
        $this->postJson('/api/master/vendors', ['name' => 'X'])->assertForbidden();
    }
}
