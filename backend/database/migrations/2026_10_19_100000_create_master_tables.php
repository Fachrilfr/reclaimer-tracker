<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data master: mesin, shift, bahan, vendor (FR-58).
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['operating', 'maintenance', 'idle'])->default('operating');
            $table->text('maintenance_reason')->nullable();
            $table->dateTime('maintenance_started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Riwayat status maintenance mesin (FR-20, FR-21)
        Schema::create('machine_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['start', 'end']);
            $table->text('reason');
            $table->foreignId('user_id')->constrained();
            $table->dateTime('logged_at');
            $table->boolean('notified_hse')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->enum('type', ['ink', 'solvent', 'other'])->default('ink');
            $table->string('unit', 20)->default('kg');
            $table->boolean('is_active')->default(true);
        });

        // Vendor pengangkut limbah B3
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('pic_name', 100)->nullable();
            $table->string('contact', 50)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Parameter sistem (PRD §8, FR-59)
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('param_key', 50)->unique();
            $table->string('param_value', 100);
            $table->string('unit', 20)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameters');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('machine_maintenance_logs');
        Schema::dropIfExists('machines');
    }
};
