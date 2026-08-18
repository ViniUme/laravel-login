<?php

use App\Http\Controllers\Api\V1\Auth\ResetPasswordAuthV1ApiController;
use App\Http\Controllers\Api\V1\Auth\SendVerifyEmailAuthV1ApiController;
use App\Http\Controllers\Api\V1\Auth\SignInAuthV1ApiController;
use App\Http\Controllers\Api\V1\Auth\SignUpAuthV1ApiController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailAuthV1ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('sign-up', SignUpAuthV1ApiController::class)->name('api.v1.auth.sign-up');
        Route::post('sign-in', SignInAuthV1ApiController::class)->middleware('throttle:5,1')->name('api.v1.auth.sign-in');
        Route::post('reset-password', ResetPasswordAuthV1ApiController::class)->middleware('throttle:5,1')->name('api.v1.auth.reset-password');

        Route::get('email/verify/{id}/{hash}', VerifyEmailAuthV1ApiController::class)
            ->middleware('signed')
            ->name('api.v1.auth.verify-email');
    });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});