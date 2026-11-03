<?php

namespace App\Support;

/**
 * Parameter yang bisa diubah di halaman Parameter (FR-59), dikelompokkan per bagian.
 * Hanya kunci yang benar-benar dibaca aplikasi; daily_reminder_hour dan
 * max_vendor_delivery_weeks belum dipakai, jadi tidak ditampilkan supaya tidak ada
 * isian yang tidak berpengaruh apa-apa.
 */
final class ParameterSettings
{
    public const GROUPS = [
        [
            'title' => 'Reclaimer',
            'keys' => [
                'target_recovery_rate' => [
                    'label' => 'Toleransi solvent', 'min' => 1, 'max' => 100, 'integer' => false,
                    'hint' => 'Persentase solvent minimum. Berlaku untuk proses yang selesai setelah diubah.',
                ],
            ],
        ],
        [
            'title' => 'Pallet dan kirim ke TPS',
            'keys' => [
                'drums_per_pallet' => [
                    'label' => 'Isi satu pallet', 'min' => 1, 'max' => 20, 'integer' => true,
                    'hint' => 'Jumlah drum per pallet.',
                ],
                'tps_delivery_days' => [
                    'label' => 'Batas kirim ke TPS', 'min' => 1, 'max' => 60, 'integer' => true,
                    'hint' => 'Sejak pallet mulai diisi. Berlaku untuk pallet yang mulai diisi setelah diubah.',
                ],
                'tps_reminder_early_days' => [
                    'label' => 'Peringatan perhatian', 'min' => 0, 'max' => 60, 'integer' => true,
                    'hint' => 'Sisa hari sebelum batas kirim ke TPS.',
                ],
                'tps_reminder_urgent_days' => [
                    'label' => 'Peringatan mendesak', 'min' => 0, 'max' => 60, 'integer' => true,
                    'hint' => 'Sisa hari sebelum batas kirim ke TPS. Harus lebih kecil dari peringatan perhatian.',
                ],
            ],
        ],
        [
            'title' => 'Masa simpan di TPS',
            'keys' => [
                'b3_max_storage_days' => [
                    'label' => 'Batas masa simpan', 'min' => 1, 'max' => 365, 'integer' => true,
                    'hint' => 'Sejak pallet sampai di TPS. Berlaku untuk pallet yang mulai diisi setelah diubah.',
                ],
                'b3_reminder_early_days' => [
                    'label' => 'Pengingat awal', 'min' => 1, 'max' => 365, 'integer' => true,
                    'hint' => 'Sisa masa simpan saat pengingat pertama dikirim ke HSE.',
                ],
                'b3_reminder_urgent_days' => [
                    'label' => 'Pengingat mendesak', 'min' => 1, 'max' => 365, 'integer' => true,
                    'hint' => 'Harus lebih kecil dari pengingat awal.',
                ],
                'b3_reminder_critical_days' => [
                    'label' => 'Pengingat kritis', 'min' => 1, 'max' => 365, 'integer' => true,
                    'hint' => 'Mulai sisa hari ini, pengingat dikirim setiap hari. Harus lebih kecil dari pengingat mendesak.',
                ],
            ],
        ],
        [
            'title' => 'Surat jalan',
            'keys' => [
                'min_pallet_per_delivery' => [
                    'label' => 'Minimal pallet per surat jalan', 'min' => 1, 'max' => 20, 'integer' => true,
                    'hint' => 'Jumlah pallet paling sedikit dalam satu serah terima ke vendor.',
                ],
            ],
        ],
    ];

    /**
     * Urutan yang harus dijaga: [kunci lebih kecil, kunci lebih besar, pesan].
     */
    public const ORDER = [
        ['tps_reminder_urgent_days', 'tps_reminder_early_days', 'Peringatan mendesak harus lebih kecil dari peringatan perhatian.'],
        ['tps_reminder_early_days', 'tps_delivery_days', 'Peringatan perhatian harus lebih kecil dari batas kirim ke TPS.'],
        ['b3_reminder_critical_days', 'b3_reminder_urgent_days', 'Pengingat kritis harus lebih kecil dari pengingat mendesak.'],
        ['b3_reminder_urgent_days', 'b3_reminder_early_days', 'Pengingat mendesak harus lebih kecil dari pengingat awal.'],
        ['b3_reminder_early_days', 'b3_max_storage_days', 'Pengingat awal harus lebih kecil dari batas masa simpan.'],
    ];

    /** @return array<string, array{label: string, min: int, max: int, integer: bool, hint: string}> */
    public static function definitions(): array
    {
        return array_merge(...array_column(self::GROUPS, 'keys'));
    }
}
