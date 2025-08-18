<?php

use App\Http\Controllers\API\ResetPasswordController;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\VerifyEmailController;

Route::group(['namespace' => 'App\Http\Controllers\API'], function () {
    // --------------- Register and Login ----------------//
    Route::post('register', 'AuthenticationController@register')->name('register');
    Route::post('login', 'AuthenticationController@login')->name('login');
    
    // ------------------ Get Data ----------------------//
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('get-user', 'AuthenticationController@userInfo')->name('get-user');
        Route::post('logout', 'AuthenticationController@logOut')->name('logout');
    });
});

Route::post('/send-otp', [VerifyEmailController::class, 'sendOtp'])
    ->name('verification.send')
    ->middleware(['throttle:6,1']);

Route::post('/verify-otp', [VerifyEmailController::class, 'verifyOtp'])
    ->name('verification.verify')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password', [ResetPasswordController::class, 'sendResetLink'])
    ->name('password.email')
    ->middleware(['throttle:6,1']);

Route::post('/forgot-password-token', [ResetPasswordController::class, 'verifyOtp'])
    ->name('password.reset')
    ->middleware(['throttle:6,1']);

Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])
    ->name('password.update')
    ->middleware(['throttle:6,1']);