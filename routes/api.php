<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyUpdateController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PhaseController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubPhaseController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Middleware\RestrictPartnerApi;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', RestrictPartnerApi::class])->group(function (): void {
    Route::get('/whoami', [AuthController::class, 'whoami']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/projects/{project}/phases', [PhaseController::class, 'index']);
    Route::post('/projects/{project}/phases', [PhaseController::class, 'store']);
    Route::put('/phases/{phase}', [PhaseController::class, 'update']);
    Route::delete('/phases/{phase}', [PhaseController::class, 'destroy']);

    Route::post('/phases/{phase}/sub-phases', [SubPhaseController::class, 'store']);
    Route::put('/sub-phases/{subPhase}', [SubPhaseController::class, 'update']);
    Route::delete('/sub-phases/{subPhase}', [SubPhaseController::class, 'destroy']);

    Route::post('/tasks', [TaskController::class, 'store']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/project', [ProjectController::class, 'show']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);

    Route::put('/tasks/{task}', [TaskController::class, 'update']);

    Route::get('/daily', [DailyUpdateController::class, 'index']);

    Route::post('/daily', [DailyUpdateController::class, 'store']);
    Route::put('/daily/{daily}', [DailyUpdateController::class, 'update']);
    Route::post('/daily/batch', [DailyUpdateController::class, 'batch']);

    Route::post('/reports/generate', [ReportController::class, 'generate']);

    Route::get('/photos', [PhotoController::class, 'index']);
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf']);
});
