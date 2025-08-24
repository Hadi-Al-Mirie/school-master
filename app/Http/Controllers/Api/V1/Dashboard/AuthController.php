<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Models\User ;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\sendPasswordResetLink;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Dashboard\LoginRequest;
use App\Http\Controllers\Controller;
class AuthController extends Controller
{


    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        if (Auth::attempt( $credentials)) {
           $user = Auth::user() ;
           if($user->role_id!=5){
                return response()->json([
                    'success'=>false,
                    'message'=>"Yoar not a maneger "
                    ],422);
           }
           $token = $user->createToken('auth_token')->plainTextToken ;

            return response()->json([
                'message' => __('dashboard/auth.success'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'role_id' => 'required|exists:roles,id',

            ],200) ;
        }

        return response()->json([
                'message' => __('dashboard/auth.email_not_verified'),
            ], 401);
       }
      public function logout(Request $request)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'success' => false
            ], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => __('dashboard/auth.logout_success'),
            'success' => true
        ]);

    } catch (\Exception $e) {
        Log::error('Logout error: ' . $e->getMessage());
        return response()->json([
            'message' => __('dashboard/auth.logout_error'),
            'success' => false,
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)],200)
            : response()->json(['message' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)],200)
            : response()->json(['message' => __($status)], 400);
    }
}
