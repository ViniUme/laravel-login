<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\SignUpAuthV1ApiController;

Route::prefix('v1')->group(function() {
    Route::prefix('auth')->group(function() {
        Route::post('sign-up', SignUpAuthV1ApiController::class)->name('auth.sign-up');
    });
});