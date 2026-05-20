<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FloorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Públicas (auth)
 */
Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'attemptPin'])->name('login.pin');
    Route::get('/admin/login', [LoginController::class, 'showAdmin'])->name('login.admin');
    Route::post('/admin/login', [LoginController::class, 'attemptAdmin'])->name('login.admin.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
 * Autenticadas
 */
Route::middleware('auth')->group(function () {
    Route::get('/floor', [FloorController::class, 'index'])->name('floor.index');

    // Stubs para los próximos pasos (UI ya los referencia)
    Route::get('/kitchen', fn () => Inertia::render('Kitchen/Placeholder'))->name('kitchen.index');
    Route::get('/menu', fn () => Inertia::render('Menu/Placeholder'))->name('menu.index');
    Route::get('/reports', fn () => Inertia::render('Reports/Placeholder'))->name('reports.index');
});
