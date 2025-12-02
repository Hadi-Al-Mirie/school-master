<?php
namespace App\Services\Dashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\User;
class AuthService
{
    public function login(array $data)
    {
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->role_id != 5) {
                return response()->json([
                    'success' => false,
                    'message' => "Yoar not a maneger "
                ], 422);
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'message' => __('dashboard/auth.success'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'role_id' => 'required|exists:roles,id'
            ], 200);
        }
        return response()->json([
            'message' => __('dashboard/auth.email_not_verified')
        ], 401);
    }
    public function logout(?User $user)
    {
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
    }
    public function sendPasswordResetLink(array $data)
    {
        $status = Password::sendResetLink([
            'email' => $data['email']
        ]);
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 200);
        }
        return response()->json(['message' => __($status)], 400);
    }
    public function resetPassword(array $data)
    {
        $status = Password::reset([
            'email' => $data['email'],
            'password' => $data['password'],
            'password_confirmation' => $data['password_confirmation'],
            'token' => $data['token']
        ], function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });
        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 200);
        }
        return response()->json(['message' => __($status)], 400);
    }
}
