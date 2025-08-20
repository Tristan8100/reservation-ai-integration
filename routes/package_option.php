<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PackageOptionController;

Route::middleware(['auth:admin-api'])->group(function () {
    Route::post('/package-options', [PackageOptionController::class, 'store']);
    Route::post('/package-options-update/{id}', [PackageOptionController::class, 'update']); //IF HAS IMAGE, DO NOT EVER EVER EVER USE PUT, PLEASE, IDK WHY BUT JUST DON'T
    Route::delete('/package-options/{id}', [PackageOptionController::class, 'destroy']);

    Route::get('/package-options-AI/{id}', [PackageOptionController::class, 'AiAnalysis']); // NOT YET CREATED
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/package-options', [PackageOptionController::class, 'index']);
    Route::get('/package-options/{id}', [PackageOptionController::class, 'show']);
});
