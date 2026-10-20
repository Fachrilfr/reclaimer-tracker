<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'status', 'maintenance_reason', 'maintenance_started_at',
        'maintenance_expected_end_at', 'maintenance_reported_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_started_at' => 'datetime',
            'maintenance_expected_end_at' => 'datetime',
        ];
    }

    /** Versi pertama hanya satu mesin reclaimer (PRD §4). */
    public static function main(): ?self
    {
        return static::orderBy('id')->first();
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maintenance_reported_by');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MachineMaintenanceLog::class);
    }

    /** Ringkasan status untuk banner, dashboard, dan form batch. */
    public function summary(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'maintenance_reason' => $this->maintenance_reason,
            'maintenance_started_at' => $this->maintenance_started_at?->toIso8601String(),
            'maintenance_expected_end_at' => $this->maintenance_expected_end_at?->toIso8601String(),
            'maintenance_reported_by' => $this->reporter?->name,
        ];
    }
}
