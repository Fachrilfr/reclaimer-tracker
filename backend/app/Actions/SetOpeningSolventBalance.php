<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ReclaimerBatch;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use App\Models\User;
use App\Support\Correction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saldo awal solvent dari hasil hitung fisik saat aplikasi mulai dipakai (Data master).
 *
 * Saldo awal adalah baris pertama buku besar solvent (penyesuaian tanpa input atau
 * pemakaian). Bila saldo awal diubah setelah ada mutasi, titik awalnya yang salah, jadi
 * semua saldo sesudahnya digeser dengan selisih yang sama: saldo di buku besar, saldo
 * setelah input, dan saldo setelah pemakaian. Alasan wajib dan tercatat di audit.
 */
class SetOpeningSolventBalance
{
    public const NOTE = 'Saldo awal solvent';

    /** Baris saldo awal, atau null bila buku besar belum punya. */
    public static function opening(): ?SolventLedger
    {
        $first = SolventLedger::orderBy('id')->first();

        return $first && $first->type === 'adjustment' && ! $first->batch_id && ! $first->solvent_usage_id ? $first : null;
    }

    /** Ringkasan untuk Data master. */
    public static function summary(): array
    {
        $opening = self::opening();
        $firstMutation = SolventLedger::when($opening, fn ($q) => $q->where('id', '>', $opening->id))->min('mutation_date');

        return [
            'amount' => $opening?->amount,
            'date' => $opening?->mutation_date->toDateString(),
            'set_by' => $opening ? User::withTrashed()->whereKey($opening->user_id)->value('name') : null,
            'current_balance' => SolventLedger::currentBalance(),
            'mutation_count' => SolventLedger::when($opening, fn ($q) => $q->where('id', '>', $opening->id))->count(),
            'first_mutation_date' => $firstMutation ? Carbon::parse($firstMutation)->toDateString() : null,
        ];
    }

    public function handle(Request $request, float $amount, string $date, ?string $reason): string
    {
        return DB::transaction(function () use ($request, $amount, $date, $reason) {
            Correction::lockSolvent();
            $opening = self::opening();
            $later = SolventLedger::when($opening, fn ($q) => $q->where('id', '>', $opening->id));
            $hasMutations = (clone $later)->exists();

            if (! $opening && $hasMutations) {
                throw ValidationException::withMessages(['amount' => 'Buku solvent tidak punya baris saldo awal. Hubungi pengembang aplikasi.']);
            }

            $first = (clone $later)->min('mutation_date');
            if ($first && $date > Carbon::parse($first)->toDateString()) {
                throw ValidationException::withMessages([
                    'date' => 'Tanggal saldo awal tidak boleh setelah mutasi solvent pertama (' . Correction::date($first) . ').',
                ]);
            }
            if ($hasMutations && ! filled($reason)) {
                throw ValidationException::withMessages([
                    'reason' => 'Sudah ada input atau pemakaian solvent. Tulis alasan mengubah saldo awal, misalnya "Hasil hitung fisik ulang".',
                ]);
            }

            $old = $opening ? ['amount' => $opening->amount, 'date' => $opening->mutation_date->toDateString()] : ['amount' => null, 'date' => null];
            $delta = round($amount - ($opening?->amount ?? 0), 2);
            if ($opening && $delta == 0 && $old['date'] === $date) {
                throw ValidationException::withMessages(['amount' => 'Saldo awal dan tanggalnya sama dengan yang tersimpan.']);
            }

            // Saldo yang pernah tercatat tidak boleh menjadi minus
            if ($delta < 0 && $hasMutations) {
                $lowest = (float) (clone $later)->min('current_balance');
                if ($lowest + $delta < 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo awal terlalu kecil: saldo solvent akan menjadi minus. Saldo awal paling sedikit '
                            . Correction::kg($opening->amount - $lowest) . ' kg.',
                    ]);
                }
            }

            $values = [
                'mutation_date' => Carbon::parse($date)->startOfDay(),
                'type' => 'adjustment',
                'amount' => $amount,
                'previous_balance' => 0,
                'current_balance' => $amount,
                'reason' => self::NOTE,
                'user_id' => $request->user()->id,
            ];
            $opening ? $opening->update($values) : $opening = SolventLedger::create($values + ['created_at' => now()]);

            if ($delta != 0 && $hasMutations) {
                // Angka hasil hitung server, bukan teks dari pengguna
                $sqlDelta = number_format($delta, 2, '.', '');
                (clone $later)->update([
                    'previous_balance' => DB::raw("previous_balance + ({$sqlDelta})"),
                    'current_balance' => DB::raw("current_balance + ({$sqlDelta})"),
                ]);
                ReclaimerBatch::withTrashed()->whereNotNull('solvent_balance_after')
                    ->update(['solvent_balance_after' => DB::raw("solvent_balance_after + ({$sqlDelta})")]);
                SolventUsage::withTrashed()->update(['balance_after' => DB::raw("balance_after + ({$sqlDelta})")]);
            }

            AuditLog::record($request, 'set_opening_balance', $opening, [
                'amount' => $amount,
                'date' => $date,
                'reason' => filled($reason) ? trim($reason) : null,
                'shifted_by' => $hasMutations ? $delta : 0,
            ], $old);

            $message = 'Saldo awal solvent ' . Correction::kg($amount) . ' kg per ' . Correction::date($date) . ' tersimpan.';
            if ($hasMutations && $delta != 0) {
                $message .= ' Semua saldo sesudahnya bergeser ' . ($delta > 0 ? '+' : '−') . Correction::kg(abs($delta)) . ' kg, saldo sekarang '
                    . Correction::kg(SolventLedger::currentBalance()) . ' kg.';
            }

            return $message;
        });
    }
}
