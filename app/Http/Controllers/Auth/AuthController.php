<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed', // 'confirmed' checks for password_confirmation field
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Create a Sanctum token for the registered user
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user,
                'token' => $token,
            ], 201); // 201 Created
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error registering user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Authenticate a user and issue a token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            // Attempt to authenticate using email and password
            if (!Auth::attempt($request->only('email', 'password'))) {
                throw ValidationException::withMessages([
                    'email' => ['Invalid credentials.'],
                ]);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Revoke old tokens to ensure only one active token per device (optional, but good practice)
            // $user->tokens()->delete(); // Uncomment this line if you want to revoke all existing tokens for the user on new login

            // Create a new Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token,
            ]); // 200 OK by default
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Authentication Failed',
                'errors' => $e->errors()
            ], 401); // 401 Unauthorized
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error during login: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Log out the authenticated user (revoke current token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Delete the current token being used for authentication
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']); // 200 OK
    }

    /**
     * Get the authenticated user details.
     * This endpoint will be protected by 'auth:sanctum' middleware
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
