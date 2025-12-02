<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LoginRequest;
use App\Http\Requests\Dashboard\Auth\SendPasswordResetLinkRequest;
use App\Http\Requests\Dashboard\Auth\ResetPasswordRequest;
use App\Services\Dashboard\AuthService;
use Illuminate\Http\Request;
class AuthController extends Controller
{
    protected AuthService $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        return $this->authService->login($data);
    }
    public function logout(Request $request)
    {
        return $this->authService->logout($request->user());
    }
    public function sendPasswordResetLink(SendPasswordResetLinkRequest $request)
    {
        $data = $request->validated();
        return $this->authService->sendPasswordResetLink($data);
    }
    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        return $this->authService->resetPassword($data);
    }
}