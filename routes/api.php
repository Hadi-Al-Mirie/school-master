<?php
use App\Http\Controllers\Api\V1\Mobile\Auth\MobileAuthController;
Route::middleware('auth:sanctum')->post('v1/logout', [MobileAuthController::class, 'logout']);
require __DIR__ . '/mobile.php';
require __DIR__ . '/dashboard.php';
