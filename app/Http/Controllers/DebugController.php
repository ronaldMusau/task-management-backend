<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DebugController extends Controller
{
    public function testJWTConfig()
    {
        return response()->json([
            'jwt_secret' => config('jwt.secret') ? 'Set (' . strlen(config('jwt.secret')) . ' chars)' : 'Not set',
            'jwt_ttl' => config('jwt.ttl'),
            'jwt_algo' => config('jwt.algo'),
            'jwt_blacklist_enabled' => config('jwt.blacklist_enabled'),
            'auth_default_guard' => config('auth.defaults.guard'),
            'auth_api_driver' => config('auth.guards.api.driver'),
            'auth_api_provider' => config('auth.guards.api.provider'),
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function testTokenGeneration()
    {
        try {
            // Get the first user
            $user = User::first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found in database'
                ], 404);
            }

            // Try to generate token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Token generated successfully',
                'user_id' => $user->id,
                'token' => $token,
                'token_length' => strlen($token),
                'payload' => JWTAuth::getPayload($token)->toArray()
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function testTokenValidation(Request $request)
    {
        try {
            // Get token from Authorization header
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No bearer token provided'
                ], 400);
            }

            // Try to parse and authenticate
            $user = JWTAuth::parseToken()->authenticate();

            return response()->json([
                'success' => true,
                'message' => 'Token is valid',
                'user' => $user,
                'token_payload' => JWTAuth::getPayload()->toArray()
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ], 401);
        }
    }

    public function testUserCredentials(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Email and password required'
            ], 400);
        }

        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $passwordMatch = Hash::check($password, $user->password);

            return response()->json([
                'success' => true,
                'user_exists' => true,
                'password_match' => $passwordMatch,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'stored_password_hash' => $user->password,
                'provided_password' => $password
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testAuthAttempt(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            $token = JWTAuth::attempt($credentials);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'token' => $token,
                'user' => auth()->user()
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function clearLogs()
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }

            return response()->json([
                'success' => true,
                'message' => 'Logs cleared'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}