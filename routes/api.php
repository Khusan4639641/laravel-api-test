<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\BinaryBonusController;
use App\Http\Controllers\Api\PackageActivationController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/ref/{user_id}/{branch}', [ReferralController::class, 'show']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/bonuses/binary/calculate', BinaryBonusController::class);
    Route::post('/packages/{package}/activate', PackageActivationController::class);
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('admin')->prefix('admin')->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::patch('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve']);
        Route::patch('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject']);
    });
});
