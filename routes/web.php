<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('chantier.index');
})->name('home');

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/projets', function () {
    return view('projects.index');
})->name('projects');

Route::redirect('/chantier', '/', 302);
