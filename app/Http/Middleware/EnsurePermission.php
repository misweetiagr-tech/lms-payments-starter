<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('perm:enrollments,view')
 * The UI may hide buttons, but this is what actually protects the action.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $section, string $action): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasPermission($section, $action)) {
            return response()->json(['error' => "Missing permission {$section}:{$action}."], 403);
        }

        return $next($request);
    }
}
