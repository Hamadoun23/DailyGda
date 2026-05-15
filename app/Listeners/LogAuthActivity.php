<?php

namespace App\Listeners;

use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof \App\Models\User) {
            return;
        }

        $viaApi = $event->guard === 'sanctum' || request()->is('api/*');

        ActivityLogger::login($user, request(), viaApi: $viaApi);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof \App\Models\User) {
            return;
        }

        $viaApi = $event->guard === 'sanctum' || request()->is('api/*');

        ActivityLogger::logout($user, request(), viaApi: $viaApi);
    }

    public function handleFailed(Failed $event): void
    {
        $username = '';
        if (is_array($event->credentials) && isset($event->credentials['username'])) {
            $username = Str::lower(trim((string) $event->credentials['username']));
        }

        if ($username === '') {
            return;
        }

        ActivityLogger::loginFailed(request(), $username);
    }
}
