<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PackageController;

Route::middleware(['auth:admin-api'])->group(function () {
    Route::post('/admin-packages', [PackageController::class, 'store']); //done
    Route::post('/admin-packages-update/{package}', [PackageController::class, 'update']); //IF HAS IMAGE, DO NOT EVER EVER EVER USE PUT, PLEASE, IDK WHY BUT JUST DON'T
    Route::delete('/admin-packages/{package}', [PackageController::class, 'destroy']);

    Route::get('/admin-packages-AI/{package}', [PackageController::class, 'AiAnalysis']); // NOT YET CREATED
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin-packages', [PackageController::class, 'index']);//done
    Route::get('/admin-packages/{package}', [PackageController::class, 'show']);//done
});
