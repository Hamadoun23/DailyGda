<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            ActivityLogger::logApiMutation($request, $response->getStatusCode());
        } catch (\Throwable) {
            // Ne pas bloquer l’API si le journal échoue.
        }

        return $response;
    }
}
