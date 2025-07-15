<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DebugController;
use Illuminate\Support\Facades\Route;

// Test route
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});

// Debug routes
Route::prefix('debug')->group(function () {
    Route::get('/jwt-config', [DebugController::class, 'testJWTConfig']);
    Route::get('/token-generation', [DebugController::class, 'testTokenGeneration']);
    Route::post('/token-validation', [DebugController::class, 'testTokenValidation']);
    Route::post('/user-credentials', [DebugController::class, 'testUserCredentials']);
    Route::post('/auth-attempt', [DebugController::class, 'testAuthAttempt']);
    Route::post('/clear-logs', [DebugController::class, 'clearLogs']);
});

// User routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// Admin routes
Route::prefix('admin/auth')->group(function () {
    Route::post('/register', [AdminAuthController::class, 'register']);
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    });
});

// Task routes (protected by appropriate middleware)
Route::middleware(['auth:api,admin'])->group(function () {
    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::post('/tasks/bulk-assign', [TaskController::class, 'bulkAssign']);
});

// User-specific task routes
Route::prefix('tasks')->middleware('auth:api')->group(function () {
    Route::get('/user', [TaskController::class, 'userTasks']);
    Route::get('/user/stats', [TaskController::class, 'userStats']);
    Route::get('/user/recent', [TaskController::class, 'recentTasks']);
});

// Admin dashboard and user listing routes
Route::middleware('auth:admin')->group(function () {
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/users', [AdminController::class, 'allUsers']);
});

Route::middleware('auth:api')->group(function () {
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
});


// User routes (protected)
Route::middleware(['auth:api,admin'])->group(function () {
    Route::post('/users/find-by-email', [UserController::class, 'findByEmail']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
    });
});
