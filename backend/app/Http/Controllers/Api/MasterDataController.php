<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReclaimerBatch;
use App\Models\Shift;
use App\Models\SolventUsage;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Data master: shift dan vendor penerima residu. Bahan tidak ditampilkan karena hanya ada
 * Tinta X (keputusan 3 Nov 2026).
 *
 * Shift yang sudah dipakai tidak bisa dihapus (riwayat input merujuk ke sana), cukup
 * dinonaktifkan. Vendor dihapus lunak, jadi surat jalan lama tetap menampilkan namanya.
 */
class MasterDataController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => self::all()]);
    }

    public function storeShift(Request $request): JsonResponse
    {
        $data = $this->validateShift($request);
        $shift = Shift::create($data + ['is_active' => true]);
        AuditLog::record($request, 'create', $shift, $shift->only(['code', 'name', 'start_time', 'end_time']));

        return $this->saved("Shift {$shift->name} ditambahkan.");
    }

    public function updateShift(Request $request, Shift $shift): JsonResponse
    {
        $data = $this->validateShift($request, $shift);
        if ($shift->is_active && ! $data['is_active'] && Shift::where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['is_active' => 'Minimal satu shift harus aktif.']);
        }
        $before = $shift->only(array_keys($data));
        $shift->update($data);
        AuditLog::record($request, 'update', $shift, $shift->only(array_keys($data)), $before);

        return $this->saved("Shift {$shift->name} tersimpan.");
    }

    /** Shift yang sudah dipakai di input atau pemakaian solvent tidak bisa dihapus, hanya dinonaktifkan. */
    public function destroyShift(Request $request, Shift $shift): JsonResponse
    {
        $used = ReclaimerBatch::where('shift_id', $shift->id)->count() + SolventUsage::where('shift_id', $shift->id)->count();
        if ($used) {
            throw ValidationException::withMessages([
                'shift' => "Shift {$shift->name} sudah dipakai di {$used} catatan, jadi tidak bisa dihapus. Nonaktifkan saja supaya tidak muncul di form.",
            ]);
        }
        if ($shift->is_active && Shift::where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['shift' => 'Minimal satu shift harus aktif.']);
        }
        AuditLog::record($request, 'delete', $shift, [], $shift->only(['code', 'name', 'start_time', 'end_time']));
        $shift->delete();

        return $this->saved("Shift {$shift->name} dihapus.");
    }

    /** Vendor dihapus lunak: surat jalan lama tetap menampilkan namanya. */
    public function destroyVendor(Request $request, Vendor $vendor): JsonResponse
    {
        AuditLog::record($request, 'delete', $vendor, [], $vendor->only(['name', 'pic_name', 'contact', 'address']));
        $vendor->delete();

        return $this->saved("Vendor {$vendor->name} dihapus.");
    }

    public function storeVendor(Request $request): JsonResponse
    {
        $vendor = Vendor::create($this->validateVendor($request) + ['is_active' => true]);
        AuditLog::record($request, 'create', $vendor, $vendor->only(['name', 'pic_name', 'contact', 'address']));

        return $this->saved("Vendor {$vendor->name} ditambahkan.");
    }

    public function updateVendor(Request $request, Vendor $vendor): JsonResponse
    {
        $data = $this->validateVendor($request, $vendor) + $request->validate(['is_active' => ['required', 'boolean']]);
        $before = $vendor->only(array_keys($data));
        $vendor->update($data);
        AuditLog::record($request, 'update', $vendor, $vendor->only(array_keys($data)), $before);

        return $this->saved("Vendor {$vendor->name} tersimpan.");
    }

    private function validateShift(Request $request, ?Shift $shift = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('shifts', 'code')->ignore($shift?->id)],
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'is_active' => [$shift ? 'required' : 'nullable', 'boolean'],
        ], [
            'code.required' => 'Isi kode shift, misalnya SHIFT-4.',
            'code.unique' => 'Kode shift ini sudah dipakai.',
            'code.max' => 'Kode shift paling panjang 20 karakter.',
            'name.required' => 'Isi nama shift.',
            'name.max' => 'Nama shift paling panjang 50 karakter.',
            'start_time.required' => 'Isi jam mulai.',
            'start_time.date_format' => 'Jam mulai tidak valid.',
            'end_time.required' => 'Isi jam selesai.',
            'end_time.date_format' => 'Jam selesai tidak valid.',
            'end_time.different' => 'Jam selesai tidak boleh sama dengan jam mulai.',
        ]);

        $result = [
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'start_time' => $data['start_time'] . ':00',
            'end_time' => $data['end_time'] . ':00',
        ];
        if ($shift) {
            $result['is_active'] = (bool) $data['is_active'];
        }

        return $result;
    }

    private function validateVendor(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('vendors', 'name')->ignore($vendor?->id)->whereNull('deleted_at')],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Isi nama vendor.',
            'name.unique' => 'Vendor dengan nama ini sudah ada.',
            'name.max' => 'Nama vendor paling panjang 150 karakter.',
            'pic_name.max' => 'Nama PIC paling panjang 100 karakter.',
            'contact.max' => 'Kontak paling panjang 50 karakter.',
            'address.max' => 'Alamat paling panjang 500 karakter.',
        ]);

        return array_map(fn ($v) => filled($v) ? trim($v) : null, $data);
    }

    private function saved(string $message): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => self::all()]);
    }

    private static function all(): array
    {
        return [
            'shifts' => Shift::orderBy('id')->get()->map(fn (Shift $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'is_active' => $s->is_active,
            ]),
            'vendors' => Vendor::orderByDesc('is_active')->orderBy('name')->get()->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'pic_name' => $v->pic_name,
                'contact' => $v->contact,
                'address' => $v->address,
                'is_active' => $v->is_active,
            ]),
        ];
    }
}
