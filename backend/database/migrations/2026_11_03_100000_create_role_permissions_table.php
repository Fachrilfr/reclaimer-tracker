<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hak akses per role yang diatur Super Admin. Baris di sini menimpa nilai awal di
     * config/access.php; level null berarti role itu tidak punya akses (menu hilang).
     * Role/fitur yang belum pernah diatur memakai nilai awal dari config.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);
            $table->string('feature', 50);
            $table->string('level', 20)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['role', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
