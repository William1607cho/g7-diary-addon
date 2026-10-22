<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Diary\Addon\Http\Controllers\CategoryController;

/*
 * g7-diary-addon plugin API routes
 *
 * URL prefix: /api/plugins/g7-diary-addon  (applied by PluginRouteServiceProvider)
 */

// Diary categories (not deleted, in order). Public display data — names, icons, colours.
Route::get('categories', [CategoryController::class, 'index'])
    ->middleware(['throttle:120,1'])
    ->name('categories.index');
