<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo akhir solvent per batch (FR-11). Salinan dari baris terakhir buku besar
     * batch tersebut, supaya daftar batch tidak perlu menelusuri buku besar.
     */
    public function up(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->decimal('solvent_balance_after', 10, 2)->nullable()->after('residue_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropColumn('solvent_balance_after');
        });
    }
};
