<?php

namespace App\Models;

use App\Models\Concerns\HasCorrection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineMaintenanceLog extends Model
{
    use HasCorrection, SoftDeletes;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime', 'notified_hse' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
