<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'status', 'maintenance_reason', 'maintenance_started_at'];

    protected function casts(): array
    {
        return ['maintenance_started_at' => 'datetime'];
    }
}
