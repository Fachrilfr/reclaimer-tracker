<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log pengingat masa simpan yang terkirim ke HSE (FR-30). */
class B3NotificationLog extends Model
{
    public $timestamps = false;

    protected $table = 'b3_notifications_log';

    protected $guarded = ['id'];

    /** Jenjang StorageStatus ke jenis pengingat (FR-27). */
    public const TYPES = ['perhatian' => 'early_30d', 'mendesak' => 'urgent_14d', 'kritis' => 'critical_7d'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'is_read' => 'boolean', 'days_remaining' => 'integer'];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(B3Pallet::class, 'pallet_id')->withTrashed();
    }
}
