<?php

namespace App\Models;

use App\Support\AccessMatrix;
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
        'password_encrypted',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'password_encrypted',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            // Salinan yang bisa dibuka Super Admin (APP_KEY); login tetap memakai hash
            'password_encrypted' => 'encrypted',
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

        return app(AccessMatrix::class)->level($this->role, $feature);
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
        return array_filter(app(AccessMatrix::class)->forRole($this->role));
    }

    /** Halaman awal role ini; bila aksesnya dicabut Super Admin, fitur pertama yang masih bisa dibuka. */
    public function homeFeature(): ?string
    {
        $permissions = $this->permissions();
        $home = config("access.home.{$this->role}");

        return isset($permissions[$home]) ? $home : array_key_first($permissions);
    }
}
