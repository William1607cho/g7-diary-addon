<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Diary\Addon\Http\Controllers\Admin\SettingsScreenController;
use Plugins\G7\Diary\Addon\Http\Controllers\CalendarController;
use Plugins\G7\Diary\Addon\Http\Controllers\CategoryController;
use Plugins\G7\Diary\Addon\Http\Controllers\MemoryController;
use Plugins\G7\Diary\Addon\Http\Controllers\PhotoController;
use Plugins\G7\Diary\Addon\Http\Controllers\SummaryController;

/*
 * g7-diary-addon plugin API routes
 *
 * URL prefix: /api/plugins/g7-diary-addon  (applied by PluginRouteServiceProvider)
 */

// Diary categories (not deleted, in order). Public display data — names, icons, colours.
Route::get('categories', [CategoryController::class, 'index'])
    ->middleware(['throttle:120,1'])
    ->name('categories.index');

// Side panel of a diary board: streak and posts per category. Board read permission is
// checked in the controller (optional.sanctum: members are recognised, guests pass).
Route::get('boards/{slug}/summary', [SummaryController::class, 'show'])
    ->middleware(['optional.sanctum', 'throttle:120,1'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('boards.summary');

// Bundle 3 screens: calendar (heatmap / month / day), photo gallery, "지난 오늘".
// Same checks as the summary (board read permission again in the controllers, D6).
Route::middleware(['optional.sanctum', 'throttle:120,1'])
    ->prefix('boards/{slug}')
    ->where(['slug' => '[A-Za-z0-9_-]+'])
    ->name('boards.')
    ->group(function () {
        Route::get('heatmap', [CalendarController::class, 'heatmap'])->name('heatmap');
        Route::get('calendar', [CalendarController::class, 'month'])->name('calendar');
        Route::get('day', [CalendarController::class, 'day'])->name('day');
        Route::get('photos', [PhotoController::class, 'index'])->name('photos');
        Route::get('memories', [MemoryController::class, 'index'])->name('memories');
    });

// Bundle 4: admin settings screen ("일기장 설정"). Only admins who may save plugin settings
// (core.plugins.update) — the same permission as the core settings save route (D111).
Route::prefix('admin')->name('admin.')
    ->middleware(['auth:sanctum', 'permission:admin,core.plugins.update', 'throttle:120,1'])
    ->group(function () {
        Route::get('screen', [SettingsScreenController::class, 'show'])->name('screen');
        Route::put('categories', [SettingsScreenController::class, 'saveCategories'])->name('categories.save');
        Route::delete('categories/{id}', [SettingsScreenController::class, 'deleteCategory'])
            ->where('id', '[0-9]+')->name('categories.delete');
    });
