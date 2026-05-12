<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', [], 401);
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        return ApiResponse::error('You do not have permission to perform this action.', [
            'permission' => [$permission],
        ], 403);
    }
}
