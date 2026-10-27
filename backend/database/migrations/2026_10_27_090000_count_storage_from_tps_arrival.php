<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Masa simpan dihitung sejak pallet sampai di TPS (pallet penuh), bukan sejak mulai
     * diisi. Pallet yang masih di area reclaimer belum punya batas simpan.
     */
    public function up(): void
    {
        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->comment('sent_to_tps_at + max_storage_days')->change();
        });

        $this->recompute('sent_to_tps_at');
    }

    public function down(): void
    {
        $this->recompute('start_date');

        Schema::table('b3_pallets', function (Blueprint $table) {
            $table->date('expiry_date')->nullable(false)->comment('start_date + max_storage_days')->change();
        });
    }

    private function recompute(string $from): void
    {
        DB::table('b3_pallets')->orderBy('id')->each(function ($pallet) use ($from) {
            DB::table('b3_pallets')->where('id', $pallet->id)->update([
                'expiry_date' => $pallet->{$from}
                    ? Carbon::parse($pallet->{$from})->addDays($pallet->max_storage_days)->toDateString()
                    : null,
            ]);
        });
    }
};
