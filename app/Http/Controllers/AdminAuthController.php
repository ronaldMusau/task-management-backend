<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Admin register endpoint accessed', ['request_data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            Log::warning('Admin registration validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Log::info('Admin created successfully', ['admin_id' => $admin->id]);

            try {
                $token = JWTAuth::customClaims([
                    'user_id' => $admin->id,
                    'role' => 'admin',
                    'email' => $admin->email
                ])->fromUser($admin);

                Log::info('JWT token generated for admin', ['admin_id' => $admin->id]);
            } catch (JWTException $e) {
                Log::error('JWT token generation failed', [
                    'error' => $e->getMessage(),
                    'admin_id' => $admin->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Admin registered successfully but token generation failed',
                    'admin' => $admin,
                    'token' => null,
                    'warning' => 'Token generation failed: ' . $e->getMessage()
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin registered successfully',
                'admin' => $admin,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 201);

        } catch (\Exception $e) {
            Log::error('Admin registration failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        Log::info('Admin login endpoint accessed', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'required|in:user,admin'
        ]);

        if ($validator->fails()) {
            Log::warning('Admin login validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        if ($request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Please use the user login endpoint'
            ], 400);
        }

        $credentials = $request->only('email', 'password');
        Log::info('Attempting admin login with credentials', ['email' => $credentials['email']]);

        $admin = Admin::where('email', $credentials['email'])->first();
        if (!$admin) {
            Log::warning('Admin login failed - admin not found', ['email' => $credentials['email']]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        Log::info('Admin found', ['admin_id' => $admin->id]);
        $passwordMatches = Hash::check($credentials['password'], $admin->password);
        Log::info('Password check result', ['matches' => $passwordMatches]);

        if (!$passwordMatches) {
            Log::warning('Admin login failed - password mismatch', ['email' => $credentials['email']]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        try {
            $token = JWTAuth::customClaims([
                'user_id' => $admin->id,
                'role' => 'admin',
                'email' => $admin->email
            ])->fromUser($admin);

            Log::info('Admin login successful', ['admin_id' => $admin->id]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'admin' => $admin,
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
        Log::info('Admin me endpoint accessed', ['admin_id' => auth('admin')->id()]);
        return response()->json([
            'success' => true,
            'admin' => auth('admin')->user()
        ]);
    }

    public function logout()
    {
        try {
            $token = JWTAuth::getToken();
            JWTAuth::invalidate($token);

            auth('admin')->logout();
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
