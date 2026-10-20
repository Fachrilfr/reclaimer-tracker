<?php

namespace App\Support;

/**
 * Rumus material balance PRD §8:
 *   input total     = Tinta X + solvent dipakai
 *   residu estimasi = input total - recovery
 *   recovery rate   = recovery / input total
 *
 * Batch lanjutan (FR-05) tidak membawa Tinta baru, jadi recovery rate-nya dihitung
 * gabungan dengan batch sebelumnya dalam rangkaian: total recovery dibagi total input.
 * Untuk batch awal, $priorInput dan $priorRecovery bernilai 0.
 */
final class MaterialBalance
{
    public function __construct(
        public readonly float $ink,
        public readonly float $solventUsed,
        public readonly float $recovery,
        public readonly float $priorInput = 0,
        public readonly float $priorRecovery = 0,
    ) {}

    public function totalInput(): float
    {
        return round($this->ink + $this->solventUsed, 2);
    }

    /**
     * Residu batch ini saja. Untuk batch lanjutan bisa negatif, karena recovery-nya
     * berasal dari bahan yang masuk di batch sebelumnya; jumlah per rangkaian tetap benar.
     */
    public function residue(): float
    {
        return round($this->totalInput() - $this->recovery, 2);
    }

    public function chainInput(): float
    {
        return round($this->priorInput + $this->totalInput(), 2);
    }

    public function chainRecovery(): float
    {
        return round($this->priorRecovery + $this->recovery, 2);
    }

    public function recoveryRate(): float
    {
        return self::rate($this->chainRecovery(), $this->chainInput());
    }

    public function residueRate(): float
    {
        return self::rate($this->chainInput() - $this->chainRecovery(), $this->chainInput());
    }

    /** FR-06: recovery tidak boleh melebihi bahan masuk. */
    public function recoveryExceedsInput(): bool
    {
        return $this->chainRecovery() > $this->chainInput();
    }

    /** FR-10: saldo berkurang oleh solvent dipakai dan bertambah dari hasil recovery. */
    public function solventBalanceAfter(float $balanceBefore): float
    {
        return round($balanceBefore - $this->solventUsed + $this->recovery, 2);
    }

    /**
     * Persentase dari jumlah total. Dipakai juga untuk periode apa pun: recovery rate
     * periode = total recovery / total input, bukan rata-rata persentase batch (PRD §8).
     */
    public static function rate(float $part, float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }
}
