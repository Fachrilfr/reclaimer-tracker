<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di route: ->middleware('akses:batch,isi')
 */
class EnsureFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature, string $level = 'lihat'): Response
    {
        if (! $request->user()?->hasAccess($feature, $level)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak punya akses untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
