<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validasi role berdasarkan enum
        $validRoles = array_map(fn($role) => UserRole::from($role)->value, $roles);

        if (!in_array($user->role, $validRoles, true)) {
            return response()->json(['error' => 'Forbidden - You do not have access'], 403);
        }

        return $next($request);
    }
}
