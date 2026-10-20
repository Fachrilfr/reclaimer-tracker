<?php

namespace App\Http\Requests;

use App\Models\ReclaimerBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Validasi bentuk data batch (FR-01 s/d FR-06). Aturan yang butuh saldo dan rangkaian
 * batch terkini (recovery vs bahan masuk, solvent vs saldo) dicek di RecordBatch,
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
            'ink_weight' => ['required', 'numeric', 'min:0', 'max:99999'],
            'solvent_used_weight' => ['required', 'numeric', 'min:0', 'max:99999'],
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

            $parent = ReclaimerBatch::find($this->input('parent_batch_id'));
            $date = Carbon::parse($this->input('date'));
            $maxDays = config('reclaimer.continuation_max_days');
            if ($parent->date->gt($date) || $parent->date->lt($date->copy()->subDays($maxDays))) {
                $validator->errors()->add('parent_batch_id', "Batch awal harus bertanggal sama atau paling lama {$maxDays} hari sebelum tanggal batch lanjutan.");
            }
        }];
    }

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
            'ink_weight.required' => 'Isi berat Tinta X dalam kg.',
            'solvent_used_weight.required' => 'Isi berat solvent dipakai dalam kg. Isi 0 bila tidak memakai solvent.',
            'recovery_weight.required' => 'Isi berat hasil recovery dalam kg. Isi 0 bila belum ada hasil.',
            'oil_temp.required' => 'Isi suhu oli (Oil Temperature).',
            'vapour_temp.required' => 'Isi suhu uap (Vapour Temperature).',
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
            'ink_weight' => round((float) $data['ink_weight'], 2),
            'solvent_used_weight' => round((float) $data['solvent_used_weight'], 2),
            'recovery_weight' => round((float) $data['recovery_weight'], 2),
            'oil_temp' => round((float) $data['oil_temp'], 2),
            'vapour_temp' => round((float) $data['vapour_temp'], 2),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
