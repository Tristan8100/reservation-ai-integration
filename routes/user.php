<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\UserController;


Route::middleware(['auth:admin-api'])->group(function () {
    Route::get('/get-users', [UserController::class, 'index']);
    Route::get('/get-user/{id}', [UserController::class, 'show']);
});

Route::middleware(['auth:user-api'])->group(function () {
    Route::get('/all-my-reservation', [UserController::class, 'getAllReservation']);
    Route::patch('/update-name', [UserController::class, 'updateName']);
    Route::patch('/change-password', [UserController::class, 'change']);
});