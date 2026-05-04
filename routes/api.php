<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BinaryBonusController;
use App\Http\Controllers\Api\PackageActivationController;
use App\Http\Controllers\Api\ReferralController;
use Illuminate\Support\Facades\Route;

Route::get('/ref/{user_id}/{branch}', [ReferralController::class, 'show']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/bonuses/binary/calculate', BinaryBonusController::class);
    Route::post('/packages/{package}/activate', PackageActivationController::class);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
