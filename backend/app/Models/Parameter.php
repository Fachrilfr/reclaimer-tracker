<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Parameter extends Model
{
    protected $fillable = ['param_key', 'param_value', 'unit', 'description', 'updated_by'];

    /**
     * Nilai parameter PRD §8 (FR-59). Parameter wajib ada karena diisi ParameterSeeder;
     * bila hilang, lebih baik gagal jelas daripada diam-diam memakai angka lain.
     */
    public static function number(string $key): float
    {
        $value = static::where('param_key', $key)->value('param_value');

        if ($value === null) {
            throw new RuntimeException("Parameter [{$key}] belum diisi. Jalankan php artisan db:seed.");
        }

        return (float) $value;
    }
}
