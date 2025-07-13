<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DebugController;
use Illuminate\Support\Facades\Route;

// Test route to check if API is working
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});

// Debug routes (remove these in production)
Route::prefix('debug')->group(function () {
    Route::get('/jwt-config', [DebugController::class, 'testJWTConfig']);
    Route::get('/token-generation', [DebugController::class, 'testTokenGeneration']);
    Route::post('/token-validation', [DebugController::class, 'testTokenValidation']);
    Route::post('/user-credentials', [DebugController::class, 'testUserCredentials']);
    Route::post('/auth-attempt', [DebugController::class, 'testAuthAttempt']);
    Route::post('/clear-logs', [DebugController::class, 'clearLogs']);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Task routes
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::post('tasks/bulk-assign', [TaskController::class, 'bulkAssign']);

    // User routes
    Route::post('/users/find-by-email', [UserController::class, 'findByEmail']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
    });
});