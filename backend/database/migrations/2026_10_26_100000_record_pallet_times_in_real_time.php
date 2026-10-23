<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pallet dicatat dengan dua tombol yang mengambil waktu saat ditekan: mulai diisi dan
     * penuh. Pallet penuh langsung tercatat terkirim ke TPS. Drum tidak dicatat satu per
     * satu lagi (riwayat drum lama tetap tersimpan).
     *
     * Waktu mulai memakai created_at; waktu penuh disimpan di full_at.
     */
    public function up(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->dateTime('full_at')->nullable()->after('full_date');
        });

        // Data lama: perkiraan waktu penuh dari tanggal penuhnya
        DB::table('b3_pallets')->whereNotNull('full_date')->update(['full_at' => DB::raw('full_date')]);
    }

    public function down(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->dropColumn('full_at');
        });
    }
};
