<?php

use App\Http\Controllers\InternalController;
use Illuminate\Support\Facades\Route;

// Внутренние API (имитация микросервисов)
Route::prefix('api/internal')->group(function () {
    Route::get('/slots', [InternalController::class, 'getSlots']);
    Route::post('/sms', [InternalController::class, 'sendSms']);
    Route::post('/email', [InternalController::class, 'sendEmail']);
});