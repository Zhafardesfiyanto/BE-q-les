<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Validate role parameters
        $validRoles = [];
        foreach ($roles as $role) {
            if (UserRole::tryFrom($role) === null) {
                abort(403, "Invalid role specified: {$role}. Valid roles are: admin, guru, murid.");
            }
            $validRoles[] = $role;
        }

        // Check if user has at least one of the required roles
        $hasRequiredRole = false;
        foreach ($validRoles as $role) {
            if ($user->role === UserRole::from($role)) {
                $hasRequiredRole = true;
                break;
            }
        }

        if (!$hasRequiredRole) {
            $userRole = $user->role->value;
            abort(403, "Unauthorized. User role: {$userRole}. Required role(s): " . implode(', ', $validRoles));
        }

        return $next($request);
    }
}