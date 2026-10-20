<?php

namespace Tests\Unit;

use App\Support\MaterialBalance;
use PHPUnit\Framework\TestCase;

class MaterialBalanceTest extends TestCase
{
    public function test_formulas_follow_prd(): void
    {
        $balance = new MaterialBalance(ink: 300, solventUsed: 200, recovery: 180);

        $this->assertSame(500.0, $balance->totalInput());
        $this->assertSame(320.0, $balance->residue());
        $this->assertSame(36.0, $balance->recoveryRate());
        $this->assertSame(64.0, $balance->residueRate());
        $this->assertSame(980.0, $balance->solventBalanceAfter(1000));
        $this->assertFalse($balance->recoveryExceedsInput());
    }

    public function test_continuation_rate_is_combined_with_earlier_batches(): void
    {
        // Batch awal: input 500, recovery 150. Lanjutan: tanpa Tinta, solvent 0, recovery 40.
        $continuation = new MaterialBalance(ink: 0, solventUsed: 0, recovery: 40, priorInput: 500, priorRecovery: 150);

        $this->assertSame(0.0, $continuation->totalInput());
        $this->assertSame(-40.0, $continuation->residue());
        $this->assertSame(38.0, $continuation->recoveryRate());
        $this->assertFalse($continuation->recoveryExceedsInput());
    }

    public function test_recovery_above_input_is_detected(): void
    {
        $this->assertTrue((new MaterialBalance(ink: 100, solventUsed: 50, recovery: 150.01))->recoveryExceedsInput());
        $this->assertFalse((new MaterialBalance(ink: 100, solventUsed: 50, recovery: 150))->recoveryExceedsInput());
    }

    public function test_period_rate_uses_totals_not_average_of_percentages(): void
    {
        // Batch A 10/100 = 10%, batch B 90/100 = 90%... rata-rata 50%. Batch C 0/800.
        // Dari total: 100 / 1000 = 10%.
        $this->assertSame(10.0, MaterialBalance::rate(10 + 90 + 0, 100 + 100 + 800));
        $this->assertSame(0.0, MaterialBalance::rate(5, 0));
    }
}
