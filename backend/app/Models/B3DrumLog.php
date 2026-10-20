<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B3DrumLog extends Model
{
    public $timestamps = false;

    protected $table = 'b3_drum_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filled_date' => 'date', 'created_at' => 'datetime'];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
