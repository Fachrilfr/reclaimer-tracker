<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengambilan solvent dari saldo hasil recovery. Tidak terkait batch: solvent yang
 * dipakai tidak masuk ke mesin reclaimer, hanya mengurangi saldo.
 */
class SolventUsage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight' => 'float',
            'balance_after' => 'float',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'shift' => $this->shift?->name,
            'weight' => $this->weight,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'balance_after' => $this->balance_after,
            'used_by' => $this->user?->name,
            'recorded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
