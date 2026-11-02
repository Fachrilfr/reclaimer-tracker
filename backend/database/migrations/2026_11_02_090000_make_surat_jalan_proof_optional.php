<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti surat jalan jadi opsional (meeting 30 Okt 2026). Nama petugas vendor wajib
     * di form, tapi kolomnya tetap nullable karena surat jalan lama bisa tanpa nama.
     */
    public function up(): void
    {
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->string('file_path_surat_jalan')->nullable()->comment('Bukti surat jalan (PDF/JPG/PNG), opsional')->change();
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->string('file_path_surat_jalan')->nullable(false)->comment('Bukti surat jalan wajib (PDF/JPG/PNG)')->change();
        });
    }
};
