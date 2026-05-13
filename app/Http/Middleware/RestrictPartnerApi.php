<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partenaires : lecture tableau de bord, tâches, rapports (liste + génération PDF), photos (lecture seule).
 * Pas de saisies du jour, pas d’upload / suppression de photos, pas de gestion projets/phases/tâches en écriture.
 */
class RestrictPartnerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isPartner()) {
            return $next($request);
        }

        $path = $request->path();
        $method = $request->method();

        if (str_starts_with($path, 'api/daily')) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (str_starts_with($path, 'api/photos') && ! in_array($method, ['GET', 'HEAD'], true)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if ($path === 'api/projects' && $method === 'GET') {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if ($path === 'api/projects' && $method === 'POST') {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (preg_match('#^api/projects/\d+$#', $path) && in_array($method, ['PUT', 'DELETE'], true)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (preg_match('#^api/projects/\d+/phases#', $path)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (preg_match('#^api/phases/\d+$#', $path) && in_array($method, ['PUT', 'DELETE'], true)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (str_starts_with($path, 'api/phases/') && str_contains($path, '/sub-phases')) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (preg_match('#^api/sub-phases/\d+$#', $path)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if ($path === 'api/tasks' && $method === 'POST') {
            abort(403, 'Accès refusé pour ce profil.');
        }

        if (preg_match('#^api/tasks/\d+$#', $path) && in_array($method, ['PUT', 'DELETE'], true)) {
            abort(403, 'Accès refusé pour ce profil.');
        }

        return $next($request);
    }
}
