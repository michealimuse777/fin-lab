<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:sensitive'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::get('/users/{id}', [UserController::class, 'showUser']);

    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::put('/users/{id}/role', [AdminController::class, 'changeRole']);
        Route::put('/users/{id}/status', [AdminController::class, 'changeStatus']);
    });
});
