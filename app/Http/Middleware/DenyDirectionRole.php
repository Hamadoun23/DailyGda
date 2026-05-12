<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyDirectionRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isDirection()) {
            return response()->json(['message' => 'Ce rôle ne peut pas effectuer cette action.'], 403);
        }

        return $next($request);
    }
}
