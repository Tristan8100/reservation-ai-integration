<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AnalyticsController;

Route::middleware(['auth:admin-api'])->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index']);
});