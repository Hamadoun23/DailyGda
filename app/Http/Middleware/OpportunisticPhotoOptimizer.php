<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déclenche la compression des photos en arrière-plan, après la réponse déjà envoyée
 * au navigateur, sur une requête d'un utilisateur connecté. Pas de cron nécessaire :
 * dès que quelqu'un utilise l'app, le "ménage" se fait tout seul, au plus une fois
 * toutes les 15 minutes (verrou cache), sans ralentir la requête qui le déclenche.
 */
class OpportunisticPhotoOptimizer
{
    private const THROTTLE_KEY = 'photos-optimize-lock';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->user()) {
            return;
        }

        if (! Cache::add(self::THROTTLE_KEY, 1, now()->addMinutes(15))) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        Artisan::call('photos:optimize');
    }
}
