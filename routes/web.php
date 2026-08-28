<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignInAuthController;

Route::inertia('/', 'Welcome')->name('home');


Route::prefix('auth')->group(function () {
    Route::get('/sign-in', SignInAuthController::class)->name('web.auth.sign-in');
});

