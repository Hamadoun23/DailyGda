<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth désactivée côté API : utilisateur par défaut (1er en base) si pas de Bearer Sanctum valide.
 */
class EnsureApiActor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $bearer = $request->bearerToken();
        if ($bearer) {
            $accessToken = PersonalAccessToken::findToken($bearer);
            if ($accessToken && $accessToken->tokenable instanceof User) {
                $request->setUserResolver(static fn () => $accessToken->tokenable);

                return $next($request);
            }
        }

        $default = User::query()->orderBy('id')->first();
        if (! $default) {
            abort(503, 'Aucun utilisateur en base : exécutez les seeders.');
        }

        $request->setUserResolver(static fn () => $default);

        return $next($request);
    }
}
