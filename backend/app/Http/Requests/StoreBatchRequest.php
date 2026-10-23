<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi bentuk data batch (FR-01 s/d FR-06). Aturan yang butuh saldo dan rangkaian
 * batch terkini (recovery vs Tinta X rangkaian) dicek di RecordBatch,
 * di dalam kunci saldo.
 */
class StoreBatchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'regu' => ['nullable', 'string', 'max:20'],
            'is_continuation' => ['required', 'boolean'],
            'parent_batch_id' => ['nullable', 'required_if:is_continuation,true', 'integer', 'exists:reclaimer_batches,id'],
            'process_status' => ['required', 'in:in_progress,completed'],
            'ink_weight' => ['required', 'numeric', 'min:0', 'max:99999'],
            'recovery_weight' => ['required', 'numeric', 'min:0', 'max:99999'],
            'oil_temp' => ['required', 'numeric', 'between:0,400'],
            'vapour_temp' => ['required', 'numeric', 'between:0,400'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->boolean('is_continuation')) {
                if ((float) $this->input('ink_weight') <= 0) {
                    $validator->errors()->add('ink_weight', 'Isi berat Tinta X yang masuk ke mesin. Untuk batch tanpa Tinta baru, pilih Batch lanjutan.');
                }

                return;
            }

            // FR-05: batch lanjutan tidak membawa Tinta baru
            if ((float) $this->input('ink_weight') > 0) {
                $validator->errors()->add('ink_weight', 'Batch lanjutan tidak membawa Tinta X baru. Kosongkan berat Tinta X, atau catat sebagai batch baru.');
            }

            // Keterangan batch lanjutan wajib mencatat jam, misalnya "Lanjutan Pkl 15.30"
            if (! preg_match(self::TIME_PATTERN, (string) $this->input('notes'))) {
                $validator->errors()->add('notes', 'Batch lanjutan wajib mencatat jam di keterangan, contoh: Lanjutan Pkl 15.30.');
            }
        }];
    }

    /** Jam 00.00 sampai 23.59, dengan titik atau titik dua. */
    public const TIME_PATTERN = '/(?<!\d)([01]?\d|2[0-3])[.:][0-5]\d(?!\d)/';

    public function messages(): array
    {
        return [
            'date.required' => 'Isi tanggal proses.',
            'date.before_or_equal' => 'Tanggal proses tidak boleh setelah hari ini.',
            'date.date_format' => 'Tanggal proses tidak valid.',
            'shift_id.required' => 'Pilih shift.',
            'shift_id.exists' => 'Shift tidak ditemukan. Pilih ulang shift.',
            'parent_batch_id.required_if' => 'Pilih batch awal yang dilanjutkan.',
            'parent_batch_id.exists' => 'Batch awal tidak ditemukan. Pilih ulang batch awal.',
            'process_status.required' => 'Pilih status proses: selesai atau belum selesai.',
            'process_status.in' => 'Status proses tidak dikenal. Pilih selesai atau belum selesai.',
            'ink_weight.required' => 'Isi berat Tinta X dalam kg.',
            'recovery_weight.required' => 'Isi berat hasil solvent dalam kg. Isi 0 bila belum ada hasil.',
            'oil_temp.required' => 'Isi suhu oli.',
            'vapour_temp.required' => 'Isi suhu uap.',
            '*.numeric' => 'Isi dengan angka, contoh 125,5.',
            '*.min' => 'Angka tidak boleh negatif.',
            '*.max' => 'Angka terlalu besar. Periksa kembali satuan kg.',
            'oil_temp.between' => 'Suhu oli di luar jangkauan alat ukur. Periksa kembali angkanya.',
            'vapour_temp.between' => 'Suhu uap di luar jangkauan alat ukur. Periksa kembali angkanya.',
            'regu.max' => 'Nama regu paling panjang 20 karakter.',
            'notes.max' => 'Keterangan paling panjang 500 karakter.',
        ];
    }

    /** Data yang sudah tervalidasi dengan tipe yang dipakai RecordBatch. */
    public function batchData(): array
    {
        $data = $this->validated();

        return [
            'date' => $data['date'],
            'shift_id' => (int) $data['shift_id'],
            'regu' => $data['regu'] ?? null,
            'is_continuation' => (bool) $data['is_continuation'],
            'parent_batch_id' => isset($data['parent_batch_id']) ? (int) $data['parent_batch_id'] : null,
            'process_status' => $data['process_status'],
            'ink_weight' => round((float) $data['ink_weight'], 2),
            'recovery_weight' => round((float) $data['recovery_weight'], 2),
            'oil_temp' => round((float) $data['oil_temp'], 2),
            'vapour_temp' => round((float) $data['vapour_temp'], 2),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
