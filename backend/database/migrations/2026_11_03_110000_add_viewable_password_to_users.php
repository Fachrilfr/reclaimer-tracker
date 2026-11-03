<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan password terenkripsi (APP_KEY) supaya Super Admin bisa memberi tahu pengguna
     * yang lupa password (keputusan pengguna, 3 Nov 2026). Login tetap memakai kolom
     * password (hash). Akun lama kosong sampai passwordnya diganti sekali.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('password_encrypted')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_encrypted');
        });
    }
};
