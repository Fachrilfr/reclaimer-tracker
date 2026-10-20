<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Isi lonceng dan banner: pemberitahuan terbaru milik pengguna, serta status
     * mesin untuk banner maintenance yang tampil ke semua pengguna.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

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
