<?php

namespace App\Models;

use App\Support\StorageStatus;
use App\Support\TpsDeliveryStatus;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class B3Pallet extends Model
{
    use SoftDeletes;

    protected $table = 'b3_pallets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'full_date' => 'date',
            'expiry_date' => 'date',
            'tps_due_date' => 'date',
            'sent_to_tps_at' => 'date',
        ];
    }

    public function drums(): HasMany
    {
        return $this->hasMany(B3DrumLog::class, 'pallet_id')->orderBy('drum_sequence');
    }

    public function suratJalan(): BelongsToMany
    {
        return $this->belongsToMany(SuratJalan::class, 'surat_jalan_pallets', 'pallet_id', 'surat_jalan_id');
    }

    /**
     * Posisi pallet: masih diisi di area reclaimer, sudah di TPS menunggu diserahkan,
     * atau sudah diserahkan ke vendor lewat surat jalan.
     */
    public function location(): string
    {
        return match (true) {
            $this->status === 'transported' => 'diserahkan',
            $this->sent_to_tps_at !== null => 'tps',
            default => 'reclaimer',
        };
    }

    /** Masa simpan di TPS sampai diserahkan ke vendor (HSE). */
    public function storageStatus(?array $thresholds = null): StorageStatus
    {
        return StorageStatus::forPallet($this, null, $thresholds);
    }

    /** Pengiriman dari area reclaimer ke TPS (operator). */
    public function tpsStatus(?array $thresholds = null, ?Collection $maintenance = null): TpsDeliveryStatus
    {
        return TpsDeliveryStatus::forPallet($this, null, $thresholds, $maintenance);
    }
}
