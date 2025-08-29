<?php

namespace App\Http\Controllers\Api\V1\Mobile\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MobileAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'message' => __('mobile/auth/auth.invalid_credentials'),
                ], 401);
            }

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => __('mobile/auth/auth.email_not_verified'),
                ], 403);
            }

            $token = $user->createToken('mobile')->plainTextToken;

            return response()->json([
                'message' => __('mobile/auth/auth.success'),
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role->name,
                    ],
                    'token' => $token,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error while login', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => __('mobile/auth/auth.server_error'),
            ], 500);
        }
    }


    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Logged out'], 200);
    }
}