<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Shift yang sedang berjalan pada waktu tertentu. Shift malam melewati
     * tengah malam, jadi jam selesai lebih kecil dari jam mulai.
     */
    public static function current(CarbonInterface $at): ?self
    {
        $time = $at->format('H:i:s');

        return static::where('is_active', true)->get()->first(function (self $shift) use ($time) {
            return $shift->start_time < $shift->end_time
                ? $time >= $shift->start_time && $time < $shift->end_time
                : $time >= $shift->start_time || $time < $shift->end_time;
        });
    }
}
