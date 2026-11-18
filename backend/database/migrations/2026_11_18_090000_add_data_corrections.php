<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koreksi data oleh Supervisor dan Super Admin (fitur "Koreksi data").
     *
     * Baris yang dikoreksi diberi tanda: kapan, oleh siapa, dan alasannya. Nilai lama
     * dan baru tercatat lengkap di audit_logs (action "correct" dan "delete").
     * Pemakaian solvent dan log maintenance yang dihapus tetap disimpan (soft delete),
     * sama seperti input reclaimer dan pallet.
     */
    private const TABLES = ['reclaimer_batches', 'solvent_usages', 'b3_pallets', 'surat_jalan', 'machine_maintenance_logs'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('corrected_at')->nullable();
                $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('correction_reason', 500)->nullable();
            });
        }

        Schema::table('solvent_usages', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('machine_maintenance_logs', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('solvent_usages', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('machine_maintenance_logs', fn (Blueprint $table) => $table->dropSoftDeletes());

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('corrected_by');
                $table->dropColumn(['corrected_at', 'correction_reason']);
            });
        }
    }
};
