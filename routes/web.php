<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

// Root route
Route::get('/', function () {
    Log::info('Root route accessed');
    return view('welcome');
});

// Debug route
Route::get('/debug', function () {
    Log::info('Debug route accessed');

    return response()->json([
        'message' => 'Debug information',
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'app_url' => config('app.url'),
        'database_connection' => config('database.default'),
        'jwt_secret_exists' => config('jwt.secret') ? 'Yes' : 'No',
        'laravel_version' => app()->version(),
        'php_version' => phpversion()
    ]);
});

// Route listing
Route::get('/routes-check', function () {
    $routes = [];
    foreach (Route::getRoutes() as $route) {
        $routes[] = [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    }

    return response()->json([
        'success' => true,
        'total_routes' => count($routes),
        'routes' => $routes,
    ], 200, [], JSON_PRETTY_PRINT);
});
