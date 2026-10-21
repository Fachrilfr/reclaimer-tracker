<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Serah terima pallet limbah B3 dari TPS ke vendor. Surat jalan adalah penutup
 * dokumen: setelah tersimpan, palletnya berstatus diserahkan.
 */
class SuratJalan extends Model
{
    use SoftDeletes;

    protected $table = 'surat_jalan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'weight_kg' => 'float',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pallets(): BelongsToMany
    {
        return $this->belongsToMany(B3Pallet::class, 'surat_jalan_pallets', 'surat_jalan_id', 'pallet_id')->orderBy('pallet_number');
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->surat_jalan_number,
            'handover_date' => $this->handover_date->toDateString(),
            'vendor' => $this->vendor?->name,
            'pallets' => $this->pallets->map(fn (B3Pallet $p) => ['id' => $p->id, 'pallet_number' => $p->pallet_number, 'drum_count' => $p->drum_count])->values()->all(),
            'total_pallets' => $this->total_pallets,
            'weight_kg' => $this->weight_kg,
            'vendor_officer_name' => $this->vendor_officer_name,
            'notes' => $this->notes,
            'file_name' => $this->file_name,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
