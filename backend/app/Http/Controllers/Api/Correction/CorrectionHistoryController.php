<?php

namespace App\Http\Controllers\Api\Correction;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Correction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Riwayat koreksi dan hapus: siapa, kapan, data mana, nilai lama dan baru, dan alasannya. */
class CorrectionHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = Correction::filters($request) + $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(Correction::ENTITIES))],
        ]);

        $page = AuditLog::with('user')
            ->whereIn('action', ['correct', 'delete'])
            ->whereIn('entity_type', array_keys(Correction::ENTITIES))
            ->when($filters['jenis'] ?? null, fn (Builder $q, $type) => $q->where('entity_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when(filled($filters['q'] ?? null), fn (Builder $q) => $q->where('new_values', 'like', Correction::like($filters['q'])))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $page->getCollection()->map(function (AuditLog $log) {
                $new = $log->new_values ?? [];
                $old = $log->old_values ?? [];
                $fields = array_values(array_unique(array_merge(array_keys($old), array_diff(array_keys($new), ['label', 'reason']))));

                return [
                    'id' => $log->id,
                    'at' => $log->created_at->toIso8601String(),
                    'by' => $log->user?->name,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity' => Correction::ENTITIES[$log->entity_type],
                    'label' => $new['label'] ?? null,
                    'reason' => $new['reason'] ?? null,
                    'changes' => array_map(fn (string $field) => [
                        'field' => $field,
                        'old' => $old[$field] ?? null,
                        'new' => $log->action === 'delete' ? null : ($new[$field] ?? null),
                    ], $fields),
                ];
            })->values(),
            'meta' => Correction::pageMeta($page) + ['entities' => Correction::ENTITIES],
        ]);
    }
}
