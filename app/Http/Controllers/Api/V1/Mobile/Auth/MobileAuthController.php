<?php
namespace App\Http\Controllers\Api\V1\Mobile\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\Auth\LoginRequest;
use App\Services\Mobile\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class MobileAuthController extends Controller
{
    protected AuthService $mobileAuthService;
    public function __construct(AuthService $mobileAuthService)
    {
        $this->mobileAuthService = $mobileAuthService;
    }
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->mobileAuthService->login($validated);
        return response()->json($result['body'], $result['status']);
    }
    public function logout(Request $request)
    {
        $user = $request->user();
        $session = $request->hasSession() ? $request->session() : null;
        $result = $this->mobileAuthService->logout($user, $session);
        return response()->json($result['body'], $result['status']);
    }
}