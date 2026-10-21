<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi bentuk catatan pemakaian solvent. Batas saldo dicek di RecordSolventUsage, di dalam kunci saldo. */
class StoreSolventUsageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'purpose' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Isi tanggal pemakaian.',
            'date.date_format' => 'Tanggal pemakaian tidak valid.',
            'date.before_or_equal' => 'Tanggal pemakaian tidak boleh setelah hari ini.',
            'shift_id.required' => 'Pilih shift.',
            'shift_id.exists' => 'Shift tidak ditemukan. Pilih ulang shift.',
            'weight.required' => 'Isi berat solvent yang diambil dalam kg.',
            'weight.numeric' => 'Isi dengan angka, contoh 125,5.',
            'weight.gt' => 'Berat solvent harus lebih dari 0 kg.',
            'weight.max' => 'Angka terlalu besar. Periksa kembali satuan kg.',
            'purpose.required' => 'Isi keperluan pemakaian solvent, contoh: pencucian mesin cetak.',
            'purpose.max' => 'Keperluan paling panjang 150 karakter.',
            'notes.max' => 'Catatan paling panjang 500 karakter.',
        ];
    }

    public function usageData(): array
    {
        $data = $this->validated();
        $notes = trim((string) ($data['notes'] ?? ''));

        return [
            'date' => $data['date'],
            'shift_id' => (int) $data['shift_id'],
            'weight' => round((float) $data['weight'], 2),
            'purpose' => trim($data['purpose']),
            'notes' => $notes === '' ? null : $notes,
        ];
    }
}
