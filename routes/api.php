<?php

use App\Http\Controllers\AcceptTransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BundlingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/bundlings', [BundlingController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {

    // Categories CRUD
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Products CRUD
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Bundlings CRUD
    Route::post('/bundlings', [BundlingController::class, 'store']);
    Route::get('/bundlings/{id}', [BundlingController::class, 'show']);
    Route::put('/bundlings/{id}', [BundlingController::class, 'update']);
    Route::delete('/bundlings/{id}', [BundlingController::class, 'destroy']);

    // Users CRUD
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}', [UserController::class, 'UpdateUser']);
    Route::post('/users', [UserController::class, 'CreateUser']);

    // Transaction CRUD
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    // Accept Transaction
    Route::post('/transactions/accept/{id}', [AcceptTransactionController::class, 'accept']);

    // Payment Checkout
    Route::post('/payments/checkout', [PaymentController::class, 'checkout']);

    // Get Me
    Route::get('/me', [UserController::class, 'getProfile']);

    // Activity Logs
    Route::get('/activity-logs', [App\Http\Controllers\ActivityLogController::class, 'index']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
