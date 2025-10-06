<?php
namespace App\Services\Mobile;

use ZEGO\ZegoServerAssistant;
use ZEGO\ZegoErrorCodes;
use Illuminate\Support\Str;

class ZegoService
{
    public function generateToken(int $userId): string
    {
        $appId = config('zego.app_id');
        $secret = config('zego.server_secret');
        $expire = config('zego.token_expire');
        $payload = '';

        $token = ZegoServerAssistant::generateToken04($appId, (string) $userId, $secret, $expire, $payload);

        if ($token->code == ZegoErrorCodes::success) {
            return $token->token;
        }

        throw new \RuntimeException('Failed to generate ZEGOCLOUD token: ' . $token->code);
    }

    public function generateChannelName(): string
    {
        return Str::uuid()->toString();
    }
}