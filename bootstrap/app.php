<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\OpportunisticPhotoOptimizer::class,
        ]);

        $middleware->redirectUsersTo(function () {
            $user = auth()->user();
            if (! $user) {
                return '/';
            }

            return $user->isPartner()
                ? route('partner.app', absolute: false)
                : route('home', absolute: false);
        });

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'admin.api' => \App\Http\Middleware\EnsureUserIsAdminApi::class,
            'log.api' => \App\Http\Middleware\LogApiActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Requête trop volumineuse (limite HTTP du serveur web, avant Laravel). '
                        .'Vérifiez nginx/apache client_max_body_size — PHP autorise souvent plus que le proxy.',
                ], 413);
            }
        });
    })->create();
