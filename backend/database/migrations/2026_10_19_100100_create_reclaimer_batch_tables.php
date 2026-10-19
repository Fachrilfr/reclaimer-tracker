<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch reclaimer (FR-01 s/d FR-08) dan buku besar saldo solvent (FR-10 s/d FR-12).
     *
     * Rumus material balance (PRD §8):
     *   input total     = Tinta X + solvent dipakai
     *   residu estimasi = input total - recovery
     *   recovery rate   = recovery / input total
     */
    public function up(): void
    {
        Schema::create('reclaimer_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->date('date');
            $table->foreignId('shift_id')->constrained();
            $table->string('regu', 20)->nullable()->comment('Regu kerja, contoh: B2, AX, C7');
            $table->foreignId('operator_id')->constrained('users');
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('material_id')->constrained()->comment('Bahan baku (Tinta X)');

            // Batch lanjutan dari shift sebelumnya tanpa Tinta baru (FR-05)
            $table->boolean('is_continuation')->default(false);
            $table->foreignId('parent_batch_id')->nullable()->constrained('reclaimer_batches')->nullOnDelete();

            // Input berat dalam kg (FR-02)
            $table->decimal('ink_weight', 10, 2)->default(0);
            $table->decimal('solvent_used_weight', 10, 2)->default(0);
            $table->decimal('recovery_weight', 10, 2)->default(0);

            // Suhu operasional, normal oli 100-160 °C dan uap 40-60 °C (FR-03)
            $table->decimal('oil_temp', 5, 2);
            $table->decimal('vapour_temp', 5, 2);
            $table->boolean('is_temp_abnormal')->default(false);

            // Hasil hitung material balance (FR-04)
            $table->decimal('total_input_weight', 10, 2)->default(0);
            $table->decimal('residue_estimated_weight', 10, 2)->default(0);
            $table->decimal('recovery_rate_percent', 5, 2)->default(0);
            $table->decimal('residue_rate_percent', 5, 2)->default(0);

            // Alert dan tinjauan supervisor (FR-45)
            $table->boolean('is_recovery_low')->default(false);
            $table->boolean('supervisor_reviewed')->default(false);
            $table->text('supervisor_notes')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
        });

        Schema::create('solvent_ledger', function (Blueprint $table) {
            $table->id();
            $table->dateTime('mutation_date');
            $table->enum('type', ['in', 'out', 'adjustment'])
                ->comment('in: hasil recovery, out: solvent dipakai, adjustment: penyesuaian manual');
            $table->decimal('amount', 10, 2);
            $table->decimal('previous_balance', 10, 2);
            $table->decimal('current_balance', 10, 2);
            $table->foreignId('batch_id')->nullable()->constrained('reclaimer_batches')->nullOnDelete();
            $table->string('reason')->nullable()->comment('Wajib diisi untuk adjustment');
            $table->foreignId('user_id')->constrained();
            $table->timestamp('created_at')->useCurrent();

            $table->index('mutation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solvent_ledger');
        Schema::dropIfExists('reclaimer_batches');
    }
};
