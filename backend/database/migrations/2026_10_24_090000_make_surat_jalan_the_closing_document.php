<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Surat jalan menjadi penutup dokumen limbah B3. Nomor manifest dan dokumen
     * penutup tidak dipakai lagi, begitu juga konfirmasi jadwal pengangkutan: HSE
     * langsung mencatat surat jalan untuk pallet yang sudah ada di TPS.
     *
     * Berat resmi dari timbangan vendor, dicatat per surat jalan dan wajib diisi.
     */
    public function up(): void
    {
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->dropColumn(['manifest_number', 'file_path_closing_doc', 'closing_doc_uploaded_at', 'status', 'drum_weight_total']);
        });
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->decimal('weight_kg', 10, 2)->default(0)->after('total_pallets')->comment('Berat dari timbangan vendor');
            $table->string('file_name')->nullable()->after('file_path_surat_jalan')->comment('Nama asli berkas bukti');
            $table->string('file_mime', 100)->nullable()->after('file_name');
            $table->index('handover_date');
        });

        // Satu pallet hanya bisa diserahkan lewat satu surat jalan
        Schema::table('surat_jalan_pallets', function (Blueprint $table) {
            $table->unique('pallet_id');
        });

        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_vendor_id');
            $table->dropColumn('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable();
            $table->foreignId('scheduled_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
        });
        // MySQL memakai index unik ini untuk foreign key pallet_id: ganti dulu dengan index biasa
        Schema::table('surat_jalan_pallets', function (Blueprint $table) {
            $table->index('pallet_id');
        });
        Schema::table('surat_jalan_pallets', function (Blueprint $table) {
            $table->dropUnique(['pallet_id']);
        });
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->dropIndex(['handover_date']);
            $table->dropColumn(['weight_kg', 'file_name', 'file_mime']);
        });
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->decimal('drum_weight_total', 10, 2)->nullable();
            $table->string('manifest_number', 100)->nullable();
            $table->string('file_path_closing_doc')->nullable();
            $table->dateTime('closing_doc_uploaded_at')->nullable();
            $table->enum('status', ['waiting_closing_doc', 'closed'])->default('waiting_closing_doc');
        });
    }
};
