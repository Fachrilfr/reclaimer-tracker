<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\B3Pallet;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Isi lonceng dan banner: pemberitahuan terbaru milik pengguna, status mesin untuk
     * banner maintenance, dan pallet yang sedang diisi. Kedua banner tampil ke semua pengguna.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Hanya satu pallet yang bisa diisi pada satu waktu (RecordPallet)
        $filling = B3Pallet::where('status', 'filling')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => in_array($user->role, config('reclaimer.maintenance_notify_roles'), true),
                'unread_count' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->latest()->limit(20)->get()
                    ->map(fn (DatabaseNotification $n) => $n->data + [
                        'id' => $n->id,
                        'read' => $n->read_at !== null,
                        'created_at' => $n->created_at->toIso8601String(),
                    ]),
                'machine' => Machine::main()?->load('reporter')->summary(),
                'filling_pallet' => $filling ? [
                    'id' => $filling->id,
                    'pallet_number' => $filling->pallet_number,
                    'drum_count' => $filling->drum_count,
                    'started_at' => $filling->created_at?->toIso8601String(),
                    'start_date' => $filling->start_date->toDateString(),
                ] : null,
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['status' => 'success']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => 'success']);
    }
}
