<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReclaimerBatch extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_continuation' => 'boolean',
            'is_temp_abnormal' => 'boolean',
            'is_recovery_low' => 'boolean',
            'supervisor_reviewed' => 'boolean',
            'ink_weight' => 'float',
            'solvent_used_weight' => 'float',
            'recovery_weight' => 'float',
            'oil_temp' => 'float',
            'vapour_temp' => 'float',
            'total_input_weight' => 'float',
            'residue_estimated_weight' => 'float',
            'recovery_rate_percent' => 'float',
            'residue_rate_percent' => 'float',
            'solvent_balance_after' => 'float',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_batch_id');
    }

    public function continuations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_batch_id');
    }
}
