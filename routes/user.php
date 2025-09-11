<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\UserController;

Route::middleware(['auth:admin-api'])->group(function () {
    Route::get('/get-users', [UserController::class, 'index']);
    Route::get('/get-user/{id}', [UserController::class, 'show']);
});