<?php

namespace Database\Seeders;

use App\Actions\RecordBatch;
use App\Actions\RecordPallet;
use App\Actions\SetMachineStatus;
use App\Models\B3Pallet;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventLedger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * DATA DUMMY untuk mencoba fitur Operator di lokal. Bukan data produksi.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Isi: batch 45 hari terakhir (sebagian dilanjutkan lintas shift, sebagian di bawah
 * target, sebagian suhu di luar normal), satu batch hari ini yang belum selesai, dan
 * pallet B3 dengan berbagai sisa masa simpan. Semua dicatat lewat RecordBatch dan
 * RecordPallet, jadi saldo solvent, rangkaian, dan jejak audit sama seperti input asli.
 * Angka acak memakai seed tetap, jadi hasilnya sama setiap kali dijalankan.
 */
class DemoDataSeeder extends Seeder
{
    private const DAYS = 45;

    // Jam simpan per shift, tanggal batch = tanggal mulai shift
    private const SAVE_TIME = ['SHIFT-1' => '14:30', 'SHIFT-2' => '22:30', 'SHIFT-3' => '23:50'];

    private Request $request;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder tidak boleh dijalankan di production.');
        }
        if (ReclaimerBatch::exists() || B3Pallet::exists()) {
            throw new RuntimeException('Database sudah berisi batch atau pallet. Jalankan php artisan migrate:fresh --seed dulu.');
        }

        $operator = User::where('nik', 'OP-1001')->firstOrFail();
        $this->request = Request::create('/demo-seed', 'POST');
        $this->request->setUserResolver(fn () => $operator);

        mt_srand(20261020);
        $realNow = now();

        // Saldo awal dari SolventOpeningBalanceSeeder bertanggal hari ini; mundurkan ke
        // sebelum riwayat dummy supaya saldo per periode di dashboard benar
        SolventLedger::where('type', 'adjustment')->whereNull('batch_id')->update([
            'mutation_date' => $realNow->copy()->subDays(self::DAYS + 1)->startOfDay(),
        ]);

        try {
            $this->seedMaintenance($realNow);
            $this->seedPallets($realNow);
            $this->seedBatches($realNow);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info(sprintf(
            'Data dummy dibuat: %d batch, %d pallet. Login sebagai OP-1001 / password123.',
            ReclaimerBatch::count(),
            B3Pallet::count(),
        ));
    }

    private function seedBatches(Carbon $realNow): void
    {
        $shifts = Shift::orderBy('id')->get()->keyBy('code');
        $record = app(RecordBatch::class);
        $pending = null; // rangkaian yang menunggu lanjutan di shift berikutnya

        for ($day = self::DAYS; $day >= 1; $day--) {
            $date = $realNow->copy()->subDays($day)->startOfDay();
            $isSunday = $date->isSunday();

            foreach (self::SAVE_TIME as $code => $time) {
                Carbon::setTestNow($date->copy()->setTimeFromTimeString($time));
                $shiftId = $shifts[$code]->id;

                if ($pending) {
                    $pending = $this->continueChain($record, $pending, $date, $shiftId);
                    continue;
                }
                // Minggu hanya shift 1; hari lain sesekali shift kosong
                if (($isSunday && $code !== 'SHIFT-1') || $this->chance(15)) {
                    continue;
                }
                $pending = $this->startChain($record, $date, $shiftId);
            }
        }

        // Rangkaian terakhir yang masih terbuka diselesaikan, lalu satu batch hari ini
        // dibiarkan belum selesai agar banner "Lanjutkan" terlihat
        if ($pending) {
            Carbon::setTestNow($realNow->copy()->subHours(2));
            $pending['final'] = true;
            $this->continueChain($record, $pending, $realNow->copy()->startOfDay(), $shifts['SHIFT-1']->id);
        }
        Carbon::setTestNow($realNow->copy()->subMinutes(30));
        $this->startChain($record, $realNow->copy()->startOfDay(), Shift::current($realNow)?->id ?? $shifts['SHIFT-1']->id, forceOpen: true);
    }

    /** Batch baru. Sekitar 20% belum selesai dan dilanjutkan shift berikutnya. */
    private function startChain(RecordBatch $record, Carbon $date, int $shiftId, bool $forceOpen = false): ?array
    {
        $ink = $this->between(260, 340);
        $solvent = $this->between(150, 200);
        // Target 35%; sekitar 12% rangkaian di bawah target
        $finalRate = $this->chance(12) ? $this->between(24, 33) : $this->between(35.5, 41.5);
        $totalRecovery = round(($ink + $solvent) * $finalRate / 100, 2);
        $open = $forceOpen || $this->chance(20);
        $legs = $open ? ($this->chance(25) ? 3 : 2) : 1;
        $firstShare = $open ? $this->between(35, 55) / 100 : 1;

        $batch = $record->handle($this->request, $this->data($date, $shiftId, [
            'ink_weight' => $ink,
            'solvent_used_weight' => $solvent,
            'recovery_weight' => round($totalRecovery * $firstShare, 2),
            'process_status' => $open ? 'in_progress' : 'completed',
            'notes' => 'Start Pkl ' . $this->clock(Carbon::now()->subHours(6)),
        ]));

        return $open ? ['root' => $batch->id, 'remaining' => round($totalRecovery * (1 - $firstShare), 2), 'legs' => $legs - 1] : null;
    }

    /** Batch lanjutan tanpa Tinta baru; menyelesaikan rangkaian di leg terakhir. */
    private function continueChain(RecordBatch $record, array $pending, Carbon $date, int $shiftId): ?array
    {
        $isLast = $pending['legs'] <= 1 || ! empty($pending['final']);
        $extraSolvent = $this->chance(40) ? $this->between(10, 40) : 0;
        // Solvent tambahan ikut menghasilkan recovery dengan rate yang sama
        $recovery = $isLast ? $pending['remaining'] + round($extraSolvent * 0.37, 2) : round($pending['remaining'] * 0.5, 2);

        $record->handle($this->request, $this->data($date, $shiftId, [
            'is_continuation' => true,
            'parent_batch_id' => $pending['root'],
            'ink_weight' => 0,
            'solvent_used_weight' => $extraSolvent,
            'recovery_weight' => $recovery,
            'process_status' => $isLast ? 'completed' : 'in_progress',
            'notes' => 'Lanjutan Pkl ' . $this->clock(Carbon::now()->subHours(7)) . ($isLast ? ', Kuras residu' : ''),
        ]));

        return $isLast ? null : ['root' => $pending['root'], 'remaining' => round($pending['remaining'] - $recovery, 2), 'legs' => $pending['legs'] - 1];
    }

    private function data(Carbon $date, int $shiftId, array $values): array
    {
        $abnormal = $this->chance(6);

        return $values + [
            'date' => $date->toDateString(),
            'shift_id' => $shiftId,
            'regu' => ['A1', 'B2', 'C3'][mt_rand(0, 2)],
            'is_continuation' => false,
            'parent_batch_id' => null,
            'oil_temp' => $abnormal ? $this->between(161, 168) : $this->between(120, 155),
            'vapour_temp' => $this->between(42, 58),
        ];
    }

    /**
     * Pallet dengan status kirim ke TPS dan sisa masa simpan TPS yang beragam.
     * Pallet baru dimulai setelah pallet sebelumnya penuh.
     */
    private function seedPallets(Carbon $realNow): void
    {
        $pallets = app(RecordPallet::class);
        // [mulai terisi (hari lalu), [drum penuh (hari lalu), ...]]
        // Pallet penuh = dikirim ke TPS. Masa simpan TPS (HSE) 90 hari dari mulai terisi.
        $plan = [
            [85, [83, 81, 76]],   // dikirim ke TPS terlambat 2 hari; di TPS sisa 5 hari: kritis
            [75, [73, 71, 69]],   // dikirim tepat waktu; di TPS sisa 15 hari: perhatian
            [66, [64, 62, 61]],   // di TPS sisa 24 hari: perhatian
            [6, [4, 2]],          // sedang diisi (2 dari 3 drum), batas kirim ke TPS besok: mendesak
        ];

        foreach ($plan as [$startAgo, $drumsAgo]) {
            Carbon::setTestNow($realNow->copy()->subDays($startAgo)->setTime(8, 0));
            $pallet = $pallets->start($this->request, Carbon::now()->toDateString(), null);

            foreach ($drumsAgo as $ago) {
                Carbon::setTestNow($realNow->copy()->subDays($ago)->setTime(15, 0));
                $pallets->addDrum($this->request, $pallet, Carbon::now()->toDateString(), null);
            }
        }
    }

    /**
     * Dua riwayat maintenance. Yang pertama bertepatan dengan pallet yang terlambat
     * dikirim ke TPS, jadi keterangan "terlambat karena mesin maintenance" ikut terlihat.
     */
    private function seedMaintenance(Carbon $realNow): void
    {
        $status = app(SetMachineStatus::class);
        $periods = [
            [79, '09:00', 77, '16:00', 'Kondensor tersumbat', 'Kondensor dibersihkan dan diuji ulang'],
            [20, '07:30', 20, '12:30', 'Pompa vakum bocor', 'Seal pompa vakum diganti'],
        ];

        foreach ($periods as [$startAgo, $startTime, $endAgo, $endTime, $reason, $note]) {
            Carbon::setTestNow($realNow->copy()->subDays($startAgo)->setTimeFromTimeString($startTime));
            $status->startMaintenance($this->request, $reason, null);
            Carbon::setTestNow($realNow->copy()->subDays($endAgo)->setTimeFromTimeString($endTime));
            $status->endMaintenance($this->request, $note);
        }

        // Riwayat lama tidak perlu muncul sebagai pemberitahuan baru di lonceng
        DatabaseNotification::query()->update(['read_at' => $realNow]);
    }

    private function between(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 2);
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    private function clock(Carbon $time): string
    {
        return $time->format('H.i');
    }
}
