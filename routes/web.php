<?php

use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\CheckItemController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\KitchenController;
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
    // Salón
    Route::get('/floor', [FloorController::class, 'index'])->name('floor.index');
    Route::post('/floor/tables/{table}/open', [CheckController::class, 'open'])->name('floor.open');

    // Comandas
    Route::get('/checks/{check}', [CheckController::class, 'show'])->name('checks.show');
    Route::post('/checks/{check}/send', [CheckController::class, 'send'])->name('checks.send');
    Route::post('/checks/{check}/close', [CheckController::class, 'close'])->name('checks.close');

    // Items de comanda
    Route::post('/checks/{check}/items', [CheckItemController::class, 'store'])->name('check-items.store');
    Route::patch('/check-items/{item}', [CheckItemController::class, 'update'])->name('check-items.update');
    Route::delete('/check-items/{item}', [CheckItemController::class, 'destroy'])->name('check-items.destroy');

    // Transiciones de estado
    Route::post('/check-items/{item}/take', [CheckItemController::class, 'take'])->name('check-items.take');
    Route::post('/check-items/{item}/ready', [CheckItemController::class, 'ready'])->name('check-items.ready');
    Route::post('/check-items/{item}/served', [CheckItemController::class, 'served'])->name('check-items.served');

    // Cocina
    Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');

    // Stubs
    Route::get('/menu', fn () => Inertia::render('Menu/Placeholder'))->name('menu.index');
    Route::get('/reports', fn () => Inertia::render('Reports/Placeholder'))->name('reports.index');

    // Admin — solo role:admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
        Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
        Route::patch('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
        Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

        Route::get('/menu-items/{menuItem}/recipe', [RecipeController::class, 'show'])->name('recipes.show');
        Route::put('/menu-items/{menuItem}/recipe', [RecipeController::class, 'upsert'])->name('recipes.upsert');
    });
});
