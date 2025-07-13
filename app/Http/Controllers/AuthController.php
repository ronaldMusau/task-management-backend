<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Register endpoint accessed', ['request_data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'sometimes|in:admin,user'
        ]);

        if ($validator->fails()) {
            Log::warning('Registration validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'user'
            ]);

            Log::info('User created successfully', ['user_id' => $user->id]);

            // Generate token with custom claims
            try {
                $token = JWTAuth::customClaims([
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'email' => $user->email
                ])->fromUser($user);

                Log::info('JWT token generated for user', ['user_id' => $user->id]);
            } catch (JWTException $e) {
                Log::error('JWT token generation failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully but token generation failed',
                    'user' => $user,
                    'token' => null,
                    'warning' => 'Token generation failed: ' . $e->getMessage()
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        Log::info('Login endpoint accessed', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            Log::warning('Login validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $credentials = $request->only('email', 'password');
        Log::info('Attempting login with credentials', ['email' => $credentials['email']]);

        // First, let's check if the user exists
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            Log::warning('Login failed - user not found', ['email' => $credentials['email']]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        Log::info('User found', ['user_id' => $user->id, 'role' => $user->role]);

        // Check password manually for debugging
        $passwordMatches = Hash::check($credentials['password'], $user->password);
        Log::info('Password check result', ['matches' => $passwordMatches]);

        if (!$passwordMatches) {
            Log::warning('Login failed - password mismatch', ['email' => $credentials['email']]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        try {
            // Generate token with custom claims
            $token = JWTAuth::customClaims([
                'user_id' => $user->id,
                'role' => $user->role,
                'email' => $user->email
            ])->fromUser($user);

            Log::info('Login successful', ['user_id' => $user->id, 'role' => $user->role]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]);

        } catch (JWTException $e) {
            Log::error('JWT creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me()
    {
        Log::info('Me endpoint accessed', ['user_id' => auth()->id()]);
        return response()->json([
            'success' => true,
            'user' => auth()->user()
        ]);
    }

    public function logout()
    {
        try {
            $token = JWTAuth::getToken();
            JWTAuth::invalidate($token);

            // Add these lines to ensure complete logout
            auth()->logout();
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}