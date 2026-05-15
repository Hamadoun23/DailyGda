<?php

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\Models\DailyUpdate;
use App\Models\Phase;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Report;
use App\Models\SubPhase;
use App\Models\Task;
use App\Observers\RecordsModelActivity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $authListener = LogAuthActivity::class;
        Event::listen(Login::class, [$authListener, 'handleLogin']);
        Event::listen(Logout::class, [$authListener, 'handleLogout']);
        Event::listen(Failed::class, [$authListener, 'handleFailed']);

        $modelObserver = RecordsModelActivity::class;
        Project::observe($modelObserver);
        Phase::observe($modelObserver);
        SubPhase::observe($modelObserver);
        Task::observe($modelObserver);
        DailyUpdate::observe($modelObserver);
        Report::observe($modelObserver);
        Photo::observe($modelObserver);
    }
}
