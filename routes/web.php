<?php

// Add these routes to your routes/web.php file

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Test admin user credentials
Route::get('/test-admin-credentials', function () {
    $email = 'admin@gmail.com';
    $password = 'Ronald123!';

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Admin user not found',
            'email_searched' => $email
        ]);
    }

    $passwordMatches = Hash::check($password, $user->password);

    return response()->json([
        'success' => true,
        'user_found' => true,
        'user_id' => $user->id,
        'user_email' => $user->email,
        'user_role' => $user->role,
        'password_matches' => $passwordMatches,
        'password_tested' => $password,
        'stored_hash' => $user->password,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at
    ]);
});

// List all users
Route::get('/list-users', function () {
    $users = User::all();

    return response()->json([
        'success' => true,
        'total_users' => $users->count(),
        'users' => $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at
            ];
        })
    ]);
});

// Test password hashing
Route::get('/test-password-hash', function () {
    $password = 'Ronald123!';
    $hash1 = Hash::make($password);
    $hash2 = Hash::make($password);

    return response()->json([
        'password' => $password,
        'hash1' => $hash1,
        'hash2' => $hash2,
        'hash1_verification' => Hash::check($password, $hash1),
        'hash2_verification' => Hash::check($password, $hash2),
        'hashes_different' => $hash1 !== $hash2
    ]);
});

// Test JWT configuration
Route::get('/test-jwt-config', function () {
    return response()->json([
        'jwt_secret_exists' => config('jwt.secret') ? 'Yes' : 'No',
        'jwt_secret_length' => strlen(config('jwt.secret') ?? ''),
        'jwt_ttl' => config('jwt.ttl'),
        'jwt_ttl_type' => gettype(config('jwt.ttl')),
        'jwt_algo' => config('jwt.algo'),
        'auth_default_guard' => config('auth.defaults.guard'),
        'auth_api_driver' => config('auth.guards.api.driver'),
        'auth_api_provider' => config('auth.guards.api.provider')
    ]);
});

// Manual login test
Route::post('/manual-login-test', function (\Illuminate\Http\Request $request) {
    $email = $request->input('email', 'admin@gmail.com');
    $password = $request->input('password', 'Ronald123!');

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
            'email' => $email
        ]);
    }

    $passwordMatches = Hash::check($password, $user->password);

    if (!$passwordMatches) {
        return response()->json([
            'success' => false,
            'message' => 'Password does not match',
            'email' => $email,
            'password_tested' => $password
        ]);
    }

    try {
        $token = \Tymon\JWTAuth\Facades\JWTAuth::customClaims([
            'user_id' => $user->id,
            'role' => $user->role,
            'email' => $user->email
        ])->fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Token generation failed',
            'error' => $e->getMessage()
        ]);
    }
});