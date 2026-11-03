<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Shift;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Data master: shift, bahan, dan vendor penerima residu.
 *
 * Tidak ada hapus permanen: shift dan vendor dipakai di riwayat input dan surat jalan,
 * jadi cukup dinonaktifkan. Bahan hanya bisa diganti nama dan satuannya, karena kode
 * TINTA-X dan SOLVENT dibaca langsung oleh perhitungan material balance.
 */
class MasterDataController extends Controller
{
    private const TYPE_LABELS = ['ink' => 'Tinta', 'solvent' => 'Solvent', 'other' => 'Lainnya'];

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

    public function updateMaterial(Request $request, Material $material): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
        ], [
            'name.required' => 'Isi nama bahan.',
            'name.max' => 'Nama bahan paling panjang 100 karakter.',
            'unit.required' => 'Isi satuan, misalnya kg.',
            'unit.max' => 'Satuan paling panjang 20 karakter.',
        ]);
        $before = $material->only(['name', 'unit']);
        $material->update(['name' => trim($data['name']), 'unit' => trim($data['unit'])]);
        AuditLog::record($request, 'update', $material, $material->only(['name', 'unit']), $before);

        return $this->saved("Bahan {$material->name} tersimpan.");
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
            'materials' => Material::orderBy('id')->get()->map(fn (Material $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'type' => self::TYPE_LABELS[$m->type] ?? $m->type,
                'unit' => $m->unit,
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
