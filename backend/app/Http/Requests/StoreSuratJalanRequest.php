<?php

namespace App\Http\Requests;

use App\Models\Parameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Surat jalan limbah B3 (FR-35 s/d FR-37). Ketersediaan pallet di TPS dicek ulang di
 * RecordSuratJalan, di dalam kunci, supaya satu pallet tidak diserahkan dua kali.
 */
class StoreSuratJalanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'surat_jalan_number' => ['required', 'string', 'max:100', Rule::unique('surat_jalan', 'surat_jalan_number')],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'handover_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'pallet_ids' => ['required', 'array', 'min:' . max(1, (int) Parameter::number('min_pallet_per_delivery'))],
            'pallet_ids.*' => ['integer', 'distinct'],
            'weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'vendor_officer_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        $min = max(1, (int) Parameter::number('min_pallet_per_delivery'));

        return [
            'surat_jalan_number.required' => 'Isi nomor surat jalan.',
            'surat_jalan_number.unique' => 'Nomor surat jalan ini sudah tercatat. Periksa kembali nomornya.',
            'surat_jalan_number.max' => 'Nomor surat jalan paling panjang 100 karakter.',
            'vendor_id.required' => 'Pilih vendor penerima.',
            'vendor_id.exists' => 'Vendor tidak ditemukan atau tidak aktif. Pilih ulang vendor.',
            'handover_date.required' => 'Isi tanggal serah terima.',
            'handover_date.date_format' => 'Tanggal serah terima tidak valid.',
            'handover_date.before_or_equal' => 'Tanggal serah terima tidak boleh setelah hari ini.',
            'pallet_ids.required' => "Pilih minimal {$min} pallet yang diserahkan.",
            'pallet_ids.min' => "Pilih minimal {$min} pallet yang diserahkan.",
            'weight_kg.required' => 'Isi berat dari timbangan vendor dalam kg.',
            'weight_kg.numeric' => 'Isi dengan angka, contoh 450,5.',
            'weight_kg.gt' => 'Berat harus lebih dari 0 kg.',
            'weight_kg.max' => 'Angka terlalu besar. Periksa kembali satuan kg.',
            'vendor_officer_name.max' => 'Nama petugas vendor paling panjang 100 karakter.',
            'notes.max' => 'Catatan paling panjang 500 karakter.',
            'file.required' => 'Unggah bukti surat jalan (PDF, JPG, atau PNG).',
            'file.uploaded' => 'Bukti gagal diunggah. Pastikan ukurannya paling besar 10 MB, lalu coba lagi.',
            'file.mimes' => 'Bukti harus berupa PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran bukti paling besar 10 MB.',
        ];
    }
}
