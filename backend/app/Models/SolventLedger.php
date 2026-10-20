<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolventLedger extends Model
{
    public $timestamps = false;

    protected $table = 'solvent_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mutation_date' => 'datetime',
            'amount' => 'float',
            'previous_balance' => 'float',
            'current_balance' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Saldo terakhir di buku besar. Pemanggil yang akan menulis mutasi harus
     * memegang kunci saldo dulu (lihat RecordBatch).
     */
    public static function currentBalance(bool $lock = false): float
    {
        $query = static::orderByDesc('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) ($query->value('current_balance') ?? 0);
    }

    /**
     * Saldo pada akhir suatu waktu, untuk saldo akhir per hari/bulan/tahun (FR-11).
     */
    public static function balanceAt(\DateTimeInterface $at): float
    {
        return (float) (static::where('mutation_date', '<=', $at)->orderByDesc('id')->value('current_balance') ?? 0);
    }
}
