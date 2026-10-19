<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'nik',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Tingkat akses role ini untuk satu fitur, atau null bila tanpa akses.
     */
    public function accessLevel(string $feature): ?string
    {
        $features = config('access.features');

        if (! array_key_exists($feature, $features)) {
            throw new InvalidArgumentException("Fitur [{$feature}] tidak ada di config/access.php.");
        }

        return $features[$feature][$this->role] ?? null;
    }

    public function hasAccess(string $feature, string $level = 'lihat'): bool
    {
        $levels = config('access.levels');

        if (! array_key_exists($level, $levels)) {
            throw new InvalidArgumentException("Tingkat akses [{$level}] tidak dikenal.");
        }

        $granted = $this->accessLevel($feature);

        return $granted !== null && in_array($level, $levels[$granted], true);
    }

    /**
     * Semua fitur yang bisa dibuka role ini beserta tingkatnya.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return collect(config('access.features'))
            ->map(fn (array $roles) => $roles[$this->role] ?? null)
            ->filter()
            ->all();
    }

    public function homeFeature(): ?string
    {
        return config("access.home.{$this->role}");
    }
}
