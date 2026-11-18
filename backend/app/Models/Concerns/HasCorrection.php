<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda "dikoreksi" pada baris data (fitur Koreksi data): kapan, oleh siapa, dan alasannya.
 * Nilai lama dan baru ada di audit_logs; di sini cukup koreksi terakhir untuk tampilan.
 */
trait HasCorrection
{
    public function initializeHasCorrection(): void
    {
        $this->mergeCasts(['corrected_at' => 'datetime']);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by')->withTrashed();
    }

    /** @return array{at: string, by: ?string, reason: ?string}|null */
    public function correctionSummary(): ?array
    {
        if (! $this->corrected_at) {
            return null;
        }

        return [
            'at' => $this->corrected_at->toIso8601String(),
            'by' => $this->corrector?->name,
            'reason' => $this->correction_reason,
        ];
    }
}
