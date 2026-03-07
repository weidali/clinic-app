<?php

use App\Http\Controllers\Api\InternalController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->group(function () {
    Route::get('/slots', [InternalController::class, 'getSlots']);
    Route::post('/sms', [InternalController::class, 'sendSms']);
    Route::post('/email', [InternalController::class, 'sendEmail']);
});
