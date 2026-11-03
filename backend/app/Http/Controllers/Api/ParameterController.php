<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Parameter;
use App\Support\ParameterSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParameterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => self::groups()]);
    }

    /** Simpan semua nilai sekaligus; hanya yang berubah yang ditulis dan dicatat di audit. */
    public function update(Request $request): JsonResponse
    {
        $definitions = ParameterSettings::definitions();
        $rules = [];
        $messages = [];
        foreach ($definitions as $key => $def) {
            $rules["values.{$key}"] = ['required', $def['integer'] ? 'integer' : 'numeric', "min:{$def['min']}", "max:{$def['max']}"];
            $messages["values.{$key}.required"] = "Isi {$def['label']}.";
            $messages["values.{$key}.integer"] = "{$def['label']} harus bilangan bulat.";
            $messages["values.{$key}.numeric"] = "{$def['label']} harus berupa angka.";
            $messages["values.{$key}.min"] = "{$def['label']} paling kecil {$def['min']}.";
            $messages["values.{$key}.max"] = "{$def['label']} paling besar {$def['max']}.";
        }
        $values = $request->validate($rules, $messages)['values'];

        $errors = [];
        foreach (ParameterSettings::ORDER as [$smaller, $larger, $message]) {
            if ((float) $values[$smaller] >= (float) $values[$larger]) {
                $errors["values.{$smaller}"] = $message;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $changed = DB::transaction(function () use ($request, $definitions, $values) {
            $changed = 0;
            foreach (Parameter::whereIn('param_key', array_keys($definitions))->lockForUpdate()->get() as $parameter) {
                $def = $definitions[$parameter->param_key];
                $new = $def['integer'] ? (string) (int) $values[$parameter->param_key] : number_format((float) $values[$parameter->param_key], 1, '.', '');
                if ((float) $new === (float) $parameter->param_value) {
                    continue;
                }
                $old = $parameter->param_value;
                $parameter->update(['param_value' => $new, 'updated_by' => $request->user()->id]);
                AuditLog::record($request, 'update', $parameter, ['param_value' => $new], ['param_value' => $old]);
                $changed++;
            }

            return $changed;
        });

        return response()->json([
            'status' => 'success',
            'message' => $changed ? "{$changed} parameter tersimpan." : 'Tidak ada perubahan.',
            'data' => self::groups(),
        ]);
    }

    private static function groups(): array
    {
        $stored = Parameter::with('updater')->whereIn('param_key', array_keys(ParameterSettings::definitions()))->get()->keyBy('param_key');

        return array_map(fn (array $group) => [
            'title' => $group['title'],
            'items' => array_values(array_filter(array_map(function (string $key) use ($group, $stored) {
                $parameter = $stored->get($key);
                if (! $parameter) {
                    return null;
                }
                $def = $group['keys'][$key];

                return [
                    'key' => $key,
                    'label' => $def['label'],
                    'hint' => $def['hint'],
                    'unit' => $parameter->unit,
                    'min' => $def['min'],
                    'max' => $def['max'],
                    'integer' => $def['integer'],
                    'value' => (float) $parameter->param_value,
                    'updated_at' => $parameter->updated_at?->toIso8601String(),
                    'updated_by' => $parameter->updater?->name,
                ];
            }, array_keys($group['keys'])))),
        ], ParameterSettings::GROUPS);
    }
}
