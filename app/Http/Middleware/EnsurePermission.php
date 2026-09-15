<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth()->user();

        if (! $user || ! collect($permissions)->contains(fn ($permission) => $user->hasPermission($permission))) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk akses ini.'], 403);
        }

        return $next($request);
    }
}