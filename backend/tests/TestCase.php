<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes perhitungan memakai berat Tinta X yang bervariasi. Berat tetap 335 kg
        // diuji tersendiri di BatchTest dengan mengaktifkan kembali nilai ini.
        config(['reclaimer.ink_weight_per_input' => null]);
    }
}
