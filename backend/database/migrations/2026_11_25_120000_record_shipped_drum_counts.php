<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalan_pallets', fn (Blueprint $table) => $table->unsignedInteger('drum_count')->nullable());
        DB::table('surat_jalan_pallets')->update([
            'drum_count' => DB::raw('(SELECT drum_count FROM b3_pallets WHERE b3_pallets.id = surat_jalan_pallets.pallet_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('surat_jalan_pallets', fn (Blueprint $table) => $table->dropColumn('drum_count'));
    }
};
