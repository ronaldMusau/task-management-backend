<?php

// Create this file as: routes/diagnostic.php
// Then include it in your routes/web.php: require_once __DIR__ . '/diagnostic.php';

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Route::get('/diagnostic', function () {
    $diagnostics = [];

    // Check Laravel installation
    $diagnostics['laravel_version'] = app()->version();
    $diagnostics['php_version'] = phpversion();
    $diagnostics['app_env'] = config('app.env');
    $diagnostics['app_debug'] = config('app.debug');
    $diagnostics['app_url'] = config('app.url');

    // Check database connection
    try {
        DB::connection()->getPdo();
        $diagnostics['database_connection'] = 'Connected';
        $diagnostics['database_name'] = DB::connection()->getDatabaseName();
    } catch (\Exception $e) {
        $diagnostics['database_connection'] = 'Failed: ' . $e->getMessage();
    }

    // Check JWT configuration
    $diagnostics['jwt_secret_exists'] = config('jwt.secret') ? 'Yes' : 'No';
    $diagnostics['jwt_ttl'] = config('jwt.ttl');

    // Check if tables exist
    try {
        $diagnostics['users_table_exists'] = DB::table('users')->exists() ? 'Yes' : 'No';
        $diagnostics['tasks_table_exists'] = DB::table('tasks')->exists() ? 'Yes' : 'No';
    } catch (\Exception $e) {
        $diagnostics['table_check'] = 'Failed: ' . $e->getMessage();
    }

    // Check routes
    $diagnostics['api_routes_loaded'] = Route::has('api.test') ? 'Yes' : 'No';

    // Check log files
    $diagnostics['log_file_writable'] = is_writable(storage_path('logs')) ? 'Yes' : 'No';

    // Check .env file
    $diagnostics['env_file_exists'] = file_exists(base_path('.env')) ? 'Yes' : 'No';

    // Check composer autoload
    $diagnostics['composer_autoload'] = file_exists(base_path('vendor/autoload.php')) ? 'Yes' : 'No';

    Log::info('Diagnostic check performed', $diagnostics);

    return response()->json([
        'success' => true,
        'diagnostics' => $diagnostics,
        'timestamp' => now()
    ], 200, [], JSON_PRETTY_PRINT);
});