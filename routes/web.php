<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\CashCloseController;
use App\Http\Controllers\Admin\CheckHistoryController;
use App\Http\Controllers\Admin\FelInvoiceController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\ModifierGroupController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TableController as AdminTableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\CheckItemController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\SplitController;
use App\Http\Controllers\TicketController;
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

    // Tip
    Route::patch('/checks/{check}/tip', [CheckController::class, 'tip'])->name('checks.tip');

    // Splits
    Route::post('/checks/{check}/splits', [SplitController::class, 'store'])->name('splits.store');
    Route::patch('/check-splits/{split}/pay', [SplitController::class, 'pay'])->name('splits.pay');
    Route::delete('/checks/{check}/splits', [SplitController::class, 'destroy'])->name('splits.destroy');

    // Stubs
    Route::get('/menu', fn () => Inertia::render('Menu/Placeholder'))->name('menu.index');

    // Print tickets (Blade — abrir en nueva pestaña)
    Route::get('/checks/{check}/ticket', [TicketController::class, 'check'])->name('tickets.check');
    Route::get('/kitchen/stations/{station}/ticket', [TicketController::class, 'station'])->name('tickets.station');

    // Admin — solo role:admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
        Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
        Route::patch('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
        Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');
        Route::post('/ingredients/{ingredient}/restock', [IngredientController::class, 'restock'])->name('ingredients.restock');

        // Cash closes
        Route::get('/cash-closes', [CashCloseController::class, 'index'])->name('cash-closes.index');
        Route::post('/cash-closes', [CashCloseController::class, 'store'])->name('cash-closes.store');

        // Modifier groups
        Route::get('/modifier-groups', [ModifierGroupController::class, 'index'])->name('modifier-groups.index');
        Route::post('/modifier-groups', [ModifierGroupController::class, 'store'])->name('modifier-groups.store');
        Route::patch('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update'])->name('modifier-groups.update');
        Route::delete('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy'])->name('modifier-groups.destroy');
        Route::post('/modifier-groups/{modifierGroup}/options', [ModifierGroupController::class, 'storeOption'])->name('modifier-groups.options.store');
        Route::patch('/modifier-groups/{modifierGroup}/options/{option}', [ModifierGroupController::class, 'updateOption'])->name('modifier-groups.options.update');
        Route::delete('/modifier-groups/{modifierGroup}/options/{option}', [ModifierGroupController::class, 'destroyOption'])->name('modifier-groups.options.destroy');

        // FEL invoices
        Route::get('/fel-invoices', [FelInvoiceController::class, 'index'])->name('fel-invoices.index');
        Route::post('/fel-invoices/{invoice}/retry', [FelInvoiceController::class, 'retry'])->name('fel-invoices.retry');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // Check history
        Route::get('/checks', [CheckHistoryController::class, 'index'])->name('checks.index');

        // Menu items
        Route::get('/menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
        Route::post('/menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
        Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update');
        Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->name('menu-items.destroy');

        // Areas
        Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
        Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
        Route::patch('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
        Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

        // Tables
        Route::get('/tables', [AdminTableController::class, 'index'])->name('tables.index');
        Route::post('/tables', [AdminTableController::class, 'store'])->name('tables.store');
        Route::patch('/tables/{table}', [AdminTableController::class, 'update'])->name('tables.update');
        Route::delete('/tables/{table}', [AdminTableController::class, 'destroy'])->name('tables.destroy');

        // Users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Recipes + modifier assignment per menu item
        Route::get('/menu-items/{menuItem}/recipe', [RecipeController::class, 'show'])->name('recipes.show');
        Route::put('/menu-items/{menuItem}/recipe', [RecipeController::class, 'upsert'])->name('recipes.upsert');
        Route::put('/menu-items/{menuItem}/modifiers', [RecipeController::class, 'syncModifiers'])->name('recipes.modifiers.sync');
    });
});
