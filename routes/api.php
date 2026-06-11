<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\SignUpAuthV1ApiController;
use App\Http\Controllers\Api\V1\Auth\SignInAuthV1ApiController;

Route::prefix('v1')->group(function() {
    Route::prefix('auth')->group(function() {
        Route::post('sign-up', SignUpAuthV1ApiController::class)->name('api.v1.auth.sign-up');
        Route::post('sign-in', SignInAuthV1ApiController::class)->middleware('throttle:5,1')->name('api.v1.auth.sign-in');
    });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});