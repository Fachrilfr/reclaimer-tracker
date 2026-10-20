<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan maintenance mesin (FR-20, FR-21): perkiraan selesai, pelapor, dan
     * pemberitahuan di aplikasi (tabel notifications bawaan Laravel).
     */
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dateTime('maintenance_expected_end_at')->nullable()->after('maintenance_started_at');
            $table->foreignId('maintenance_reported_by')->nullable()->after('maintenance_expected_end_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('machines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_reported_by');
            $table->dropColumn('maintenance_expected_end_at');
        });
    }
};
