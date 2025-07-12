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

            $token = JWTAuth::fromUser($user);
            Log::info('JWT token generated for user', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user,
                'token' => $token
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
            'password' => 'required|string|min:6'
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

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                Log::warning('Login failed - invalid credentials', ['email' => $credentials['email']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            Log::info('Login successful', ['user_id' => auth()->id()]);
        } catch (JWTException $e) {
            Log::error('JWT creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Could not create token'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => auth()->user(),
            'token' => $token
        ]);
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
        Log::info('Logout endpoint accessed', ['user_id' => auth()->id()]);

        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            Log::info('User logged out successfully', ['user_id' => auth()->id()]);
        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
}