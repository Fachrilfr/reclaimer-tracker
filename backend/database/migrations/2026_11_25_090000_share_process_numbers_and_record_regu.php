<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solvent_usages', fn (Blueprint $table) => $table->string('regu', 1)->nullable());
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropUnique(['batch_number']);
            $table->index('batch_number');
            $table->string('previous_batch_number', 50)->nullable()->unique();
        });
        DB::table('reclaimer_batches')->whereNotNull('parent_batch_id')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $rootNumber = DB::table('reclaimer_batches')->where('id', $row->parent_batch_id)->value('batch_number');
                DB::table('reclaimer_batches')->where('id', $row->id)->update([
                    'previous_batch_number' => $row->batch_number,
                    'batch_number' => $rootNumber,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('reclaimer_batches')->whereNotNull('parent_batch_id')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('reclaimer_batches')->where('id', $row->id)->update([
                    'batch_number' => $row->previous_batch_number ?? $row->batch_number.'-L'.$row->id,
                ]);
            }
        });
        Schema::table('reclaimer_batches', function (Blueprint $table) {
            $table->dropUnique(['previous_batch_number']);
            $table->dropIndex(['batch_number']);
            $table->dropColumn('previous_batch_number');
            $table->unique('batch_number');
        });
        Schema::table('solvent_usages', fn (Blueprint $table) => $table->dropColumn('regu'));
    }
};
