<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Fallback: all other paths go to the Vue SPA (Vue Router handles routing).
 * This must be the LAST route in this file.
 */
Route::fallback(function () {
    return view('welcome');
});
