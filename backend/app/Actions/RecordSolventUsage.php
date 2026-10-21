<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Material;
use App\Models\SolventLedger;
use App\Models\SolventUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Catat pengambilan solvent dari saldo, beserta mutasi keluar di buku besar (FR-10). */
class RecordSolventUsage
{
    /** @param  array{date: string, shift_id: int, weight: float, purpose: string, notes: ?string}  $data */
    public function handle(Request $request, array $data): SolventUsage
    {
        return DB::transaction(function () use ($request, $data) {
            // Kunci saldo yang sama dengan RecordBatch, supaya mutasi tidak saling menimpa
            Material::where('code', 'SOLVENT')->lockForUpdate()->firstOrFail();
            $balance = SolventLedger::currentBalance(lock: true);

            if ($data['weight'] > $balance) {
                throw ValidationException::withMessages([
                    'weight' => sprintf('Solvent yang diambil melebihi saldo (%s kg). Periksa kembali angkanya.', number_format($balance, 2, ',', '.')),
                ]);
            }

            $after = round($balance - $data['weight'], 2);
            $usage = SolventUsage::create($data + ['balance_after' => $after, 'used_by' => $request->user()->id]);

            SolventLedger::create([
                'mutation_date' => now(),
                'type' => 'out',
                'amount' => $data['weight'],
                'previous_balance' => $balance,
                'current_balance' => $after,
                'solvent_usage_id' => $usage->id,
                'user_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            AuditLog::record($request, 'create', $usage, $usage->only(['date', 'shift_id', 'weight', 'purpose', 'notes', 'balance_after']));

            return $usage;
        });
    }
}
