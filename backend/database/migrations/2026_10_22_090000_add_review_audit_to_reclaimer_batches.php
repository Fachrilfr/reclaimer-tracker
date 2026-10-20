<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tinjauan supervisor untuk rangkaian di bawah target recovery (FR-45, hak Tinjau
     * di PRD §5.1). Disimpan di batch awal rangkaian bersama supervisor_reviewed dan
     * supervisor_notes yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->foreignId('supervisor_reviewed_by')->nullable()->after('supervisor_notes')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropColumn('supervisor_reviewed_at');
        });
    }
};
