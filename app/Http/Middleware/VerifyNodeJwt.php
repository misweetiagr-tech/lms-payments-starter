<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Accepts the access token issued by the separate Node auth service, so one
 * login works against both backends. Additive: the routes using it live under
 * /api/app and the existing routes are untouched.
 */
class VerifyNodeJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('nodeauth.secret');
        $token = $request->bearerToken();

        if ($secret === '') {
            return response()->json(['error' => 'Auth bridge not configured.'], 500);
        }

        if (! $token) {
            return response()->json(['error' => 'Missing token.'], 401);
        }

        try {
            JWT::$leeway = (int) config('nodeauth.leeway', 30);
            $claims = JWT::decode($token, new Key($secret, config('nodeauth.algo', 'HS256')));
        } catch (Throwable) {
            // Bad signature, expired, malformed: all the same answer to the caller.
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        // A refresh token must never work as an access token.
        if (($claims->token_type ?? null) !== 'access') {
            return response()->json(['error' => 'Wrong token type.'], 401);
        }

        $user = User::find($claims->sub ?? null);
        if (! $user) {
            return response()->json(['error' => 'Unknown user.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
