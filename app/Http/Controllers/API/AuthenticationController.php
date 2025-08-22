<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\EmailVerification;

class AuthenticationController extends Controller
{
    /**
     * Register a new account.
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|min:4',
                'email'    => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

           
            // Generate 6-digit OTP
            $otp = rand(100000, 999999);

            // Create or update the OTP record
            EmailVerification::updateOrCreate(
                ['email' => $user->email],
                [
                    'otp' => $otp,
                    'verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Send OTP email (using Laravel mail)
            $userEmail = $user->email;
            Mail::raw("Your verification OTP is: $otp. It expires in 10 minutes.", function ($message) use ($userEmail) {
                $message->to($userEmail)
                        ->subject('Email Verification OTP');
            });

            return response()->json(['message' => 'OTP sent to your email.', 'email' => $user->email]);

        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'Validation failed',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Registration failed',
            ], 500);
        }
    }

    /**
     * Login and return auth token.
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'response_code' => 401,
                    'status'        => 'error',
                    'message'       => 'Unauthorized',
                ], 401);
            }

            $user = User::where('email', $credentials['email'])->first();
            $user->tokens()->delete();
            $token = $user->createToken('admin-auth-token')->plainTextToken;

            return response()->json([
                'response_code' => 200,
                'status'        => 'success',
                'message'       => 'Login successful',
                'user_info'     => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token'       => $token,
                'token_type'  => 'Bearer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'Validation failed',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Login failed',
            ], 500);
        }
    }

    /**
     * Get list of users (paginated) — protected route.
     */
    public function userInfo()
    {
        try {
            $users = User::latest()->paginate(10);

            return response()->json([
                'response_code'  => 200,
                'status'         => 'success',
                'message'        => 'Fetched user list successfully',
                'data_user_list' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('User List Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Failed to fetch user list',
            ], 500);
        }
    }

    /**
     * Logout user and revoke tokens — protected route.
     */
    public function logOut(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->tokens()->delete();

                return response()->json([
                    'response_code' => 200,
                    'status'        => 'success',
                    'message'       => 'Successfully logged out',
                ]);
            }

            return response()->json([
                'response_code' => 401,
                'status'        => 'error',
                'message'       => 'User not authenticated',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'An error occurred during logout',
            ], 500);
        }
    }

    public function user(Request $request)
    {
        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'User data fetched successfully',
            'user_info'     => $request->user(),
        ]);
    }
}