<?php

use Illuminate\Support\Facades\Route;
use App\Http\Contrllers\Auth\SignUpAuthController;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('auth')->group(function () {
    Route::get('sign-up', SignUpAuthController::class)->name('web.auth.sign-up');
});
