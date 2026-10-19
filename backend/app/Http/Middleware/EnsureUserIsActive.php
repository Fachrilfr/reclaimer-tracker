<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token yang dibuat sebelum akun dinonaktifkan tetap valid di Sanctum,
 * jadi status aktif dicek ulang di setiap request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Akun dinonaktifkan. Silakan hubungi Administrator.',
            ], 401);
        }

        return $next($request);
    }
}
