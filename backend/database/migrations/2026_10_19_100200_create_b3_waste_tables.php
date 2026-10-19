<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limbah B3: pallet, drum, pengingat masa simpan (FR-25 s/d FR-32),
     * serta surat jalan dan dokumen penutup (FR-35 s/d FR-40).
     */
    public function up(): void
    {
        // 1 pallet = 3 drum, masa simpan default 90 hari sejak pallet mulai terisi
        Schema::create('b3_pallets', function (Blueprint $table) {
            $table->id();
            $table->string('pallet_number', 50)->unique()->comment('Contoh: PLT-202610-001');
            $table->unsignedTinyInteger('drum_count')->default(0);
            $table->date('start_date')->comment('Titik awal hitungan masa simpan');
            $table->date('full_date')->nullable();
            $table->unsignedSmallInteger('max_storage_days')->default(90);
            $table->date('expiry_date')->comment('start_date + max_storage_days');
            $table->enum('status', ['filling', 'full', 'scheduled', 'transported'])->default('filling');

            // Konfirmasi jadwal pengangkutan (FR-29)
            $table->date('scheduled_date')->nullable();
            $table->foreignId('scheduled_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expiry_date']);
        });

        Schema::create('b3_drum_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('b3_pallets')->cascadeOnDelete();
            $table->unsignedTinyInteger('drum_sequence')->comment('Drum ke-1, ke-2, atau ke-3');
            $table->date('filled_date');
            $table->foreignId('operator_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pallet_id', 'drum_sequence']);
        });

        // Jenjang pengingat: awal (sisa 30 hari), mendesak (14), kritis (7)
        Schema::create('b3_notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('b3_pallets')->cascadeOnDelete();
            $table->enum('notification_type', ['early_30d', 'urgent_14d', 'critical_7d', 'machine_maintenance']);
            $table->integer('days_remaining')->comment('Sisa hari eksak, negatif bila lewat batas');
            $table->string('recipient_role', 50)->default('hse');
            $table->text('message');
            $table->dateTime('sent_at');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // Status: menunggu dokumen penutup, lalu ditutup
        Schema::create('surat_jalan', function (Blueprint $table) {
            $table->id();
            $table->string('surat_jalan_number', 100)->unique();
            $table->foreignId('vendor_id')->constrained();
            $table->date('handover_date');
            $table->unsignedSmallInteger('total_pallets')->default(1);
            $table->decimal('drum_weight_total', 10, 2)->nullable();
            $table->string('vendor_officer_name', 100)->nullable();
            $table->string('file_path_surat_jalan')->comment('Bukti surat jalan wajib (PDF/JPG/PNG)');

            // Nomor manifest dan dokumen penutup boleh menyusul (FR-39)
            $table->string('manifest_number', 100)->nullable();
            $table->string('file_path_closing_doc')->nullable();
            $table->dateTime('closing_doc_uploaded_at')->nullable();
            $table->enum('status', ['waiting_closing_doc', 'closed'])->default('waiting_closing_doc');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('surat_jalan_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_jalan_id')->constrained('surat_jalan')->cascadeOnDelete();
            $table->foreignId('pallet_id')->constrained('b3_pallets');

            $table->unique(['surat_jalan_id', 'pallet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_jalan_pallets');
        Schema::dropIfExists('surat_jalan');
        Schema::dropIfExists('b3_notifications_log');
        Schema::dropIfExists('b3_drum_logs');
        Schema::dropIfExists('b3_pallets');
    }
};
