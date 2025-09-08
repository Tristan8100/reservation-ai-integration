<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ReservationController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);
    Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    Route::get('/reservations/user/{userId}', [ReservationController::class, 'userReservations']);
});

Route::middleware(['auth:user-api'])->group(function () {
    Route::post('/reservations/{id}/review', [ReservationController::class, 'submitReview']); // Submit review, AI will be called UPDATE: DONE
    Route::post('/reservations', [ReservationController::class, 'store']); //create a reservation
    Route::get('/my-reservations', [ReservationController::class, 'authUserReservations']); //update a reservation

    Route::get('/my-reservations-status', [ReservationController::class, 'authUserReservationsStatus']); //update a reservation
    Route::patch('/my-reservations-update/{id}', [ReservationController::class, 'cancel']); //cancel a reservation
});

Route::middleware(['auth:admin-api'])->group(function () {
    Route::put('/reservations-status/{id}', [ReservationController::class, 'updateStatus']); //update status //IF ISSUE CHECK AGAIN
    Route::get('/reservations-status', [ReservationController::class, 'allReservationsStatus']); //get all reservations with status filter
});


