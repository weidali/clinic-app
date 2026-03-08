<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\InternalController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->group(function () {
    Route::get('/slots', [InternalController::class, 'getSlots']);
    Route::post('/sms', [InternalController::class, 'sendSms']);
    Route::post('/email', [InternalController::class, 'sendEmail']);
});


Route::get('/doctors/search', [AppointmentController::class, 'searchDoctors']);
Route::get('/slots', [AppointmentController::class, 'getSlots']);
Route::post('/appointments', [AppointmentController::class, 'store']);
