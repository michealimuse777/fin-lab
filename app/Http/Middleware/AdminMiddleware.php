<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($user->role !== 'admin') {
            SecurityLog::warning('admin_access_attempt', $request, 403, 'admin_area', null, [
                'reason' => 'actor_role_not_admin',
            ]);

            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
