<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Diary\Addon\Http\Controllers\CategoryController;
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
