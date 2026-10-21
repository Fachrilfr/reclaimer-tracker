<?php

namespace Tests\Unit;

use App\Support\MaterialBalance;
use PHPUnit\Framework\TestCase;

class MaterialBalanceTest extends TestCase
{
    public function test_input_is_ink_only(): void
    {
        $balance = new MaterialBalance(ink: 300, recovery: 120);

        $this->assertSame(300.0, $balance->totalInput());
        $this->assertSame(180.0, $balance->residue());
        $this->assertSame(40.0, $balance->recoveryRate());
        $this->assertSame(60.0, $balance->residueRate());
        // Hasil recovery menambah saldo; pemakaian solvent dicatat terpisah
        $this->assertSame(1120.0, $balance->solventBalanceAfter(1000));
        $this->assertFalse($balance->recoveryExceedsInput());
    }

    public function test_continuation_rate_is_combined_with_earlier_batches(): void
    {
        // Batch awal: Tinta X 500, recovery 150. Lanjutan: tanpa Tinta, recovery 40.
        $continuation = new MaterialBalance(ink: 0, recovery: 40, priorInput: 500, priorRecovery: 150);

        $this->assertSame(0.0, $continuation->totalInput());
        $this->assertSame(-40.0, $continuation->residue());
        $this->assertSame(38.0, $continuation->recoveryRate());
        $this->assertFalse($continuation->recoveryExceedsInput());
    }

    public function test_recovery_above_ink_is_detected(): void
    {
        $this->assertTrue((new MaterialBalance(ink: 100, recovery: 100.01))->recoveryExceedsInput());
        $this->assertFalse((new MaterialBalance(ink: 100, recovery: 100))->recoveryExceedsInput());
    }

    public function test_period_rate_uses_totals_not_average_of_percentages(): void
    {
        // Batch A 10/100 = 10%, batch B 90/100 = 90%... rata-rata 50%. Batch C 0/800.
        // Dari total: 100 / 1000 = 10%.
        $this->assertSame(10.0, MaterialBalance::rate(10 + 90 + 0, 100 + 100 + 800));
        $this->assertSame(0.0, MaterialBalance::rate(5, 0));
    }
}
