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
            'chain_completed_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'chain_recovery_rate' => 'float',
            'target_recovery_rate' => 'float',
        ];
    }

    public function isInProgress(): bool
    {
        return $this->process_status === 'in_progress';
    }

    /** Batch awal rangkaian: dirinya sendiri, atau batch yang ditunjuk parent_batch_id. */
    public function root(): self
    {
        return $this->parent_batch_id ? $this->parent : $this;
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    /** Tinjauan supervisor atas rangkaian ini (disimpan di batch awal), atau null. */
    public function reviewSummary(): ?array
    {
        if (! $this->supervisor_reviewed) {
            return null;
        }

        return [
            'notes' => $this->supervisor_notes,
            'by' => $this->reviewer?->name,
            'at' => $this->supervisor_reviewed_at?->toIso8601String(),
        ];
    }

    public function continuations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_batch_id')->orderBy('id');
    }
}
