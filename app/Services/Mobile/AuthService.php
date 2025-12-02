<?php
namespace App\Services\Mobile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;
class AuthService
{
    public function login(array $data)
    {
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        if (!Auth::attempt($credentials)) {
            return [
                'status' => 401,
                'body' => [
                    'message' => __('mobile/auth/auth.invalid_credentials')
                ]
            ];
        }
        $user = Auth::user();
        if (!$user) {
            return [
                'status' => 403,
                'body' => [
                    'message' => __('mobile/auth/auth.email_not_verified')
                ]
            ];
        }
        $token = $user->createToken('mobile')->plainTextToken;
        return [
            'status' => 200,
            'body' => [
                'message' => __('mobile/auth/auth.success'),
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role->name
                    ],
                    'token' => $token
                ]
            ]
        ];
    }
    public function logout(User $user, $session = null)
    {
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            }
        }
        if ($session) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();
        }
        return [
            'status' => 200,
            'body' => [
                'message' => 'Logged out'
            ]
        ];
    }
}
