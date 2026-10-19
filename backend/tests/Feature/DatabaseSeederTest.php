<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_master_data_and_parameters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('machines', 1);
        $this->assertDatabaseCount('shifts', 3);
        $this->assertDatabaseCount('materials', 2);
        $this->assertDatabaseCount('vendors', 2);
        $this->assertDatabaseCount('parameters', 9);
        $this->assertDatabaseHas('parameters', ['param_key' => 'target_recovery_rate', 'param_value' => '35.0']);
        $this->assertDatabaseHas('solvent_ledger', ['type' => 'adjustment', 'current_balance' => 1000]);
    }

    public function test_seeder_can_run_twice_without_duplicates_or_overwriting_parameters(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('parameters')->where('param_key', 'target_recovery_rate')->update(['param_value' => '40.0']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('vendors', 2);
        $this->assertDatabaseCount('parameters', 9);
        $this->assertDatabaseCount('solvent_ledger', 1);
        $this->assertDatabaseHas('parameters', ['param_key' => 'target_recovery_rate', 'param_value' => '40.0']);
    }
}
