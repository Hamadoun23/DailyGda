<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/fichiers/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('gda.public-file');

Route::redirect('/chantier', '/', 302);

Route::get('/', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }
    if ($user->isPartner()) {
        return redirect()->route('partner.app');
    }

    return view('chantier.index');
})->middleware('auth')->name('home');

Route::middleware(['auth'])->get('/partner', function () {
    if (! auth()->user()->isPartner()) {
        return redirect()->route('home');
    }

    return view('partner.index');
})->name('partner.app');

Route::get('/projets', function () {
    return view('projects.index');
})->middleware(['auth', 'admin'])->name('projects');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
