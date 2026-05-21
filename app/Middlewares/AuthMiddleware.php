<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Services\TokenService;

class AuthMiddleware
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function requireAuth(Request $request): array
    {
        $plainToken = $request->bearerToken();

        $result = $this->tokenService->verifyAccessToken($plainToken);

        if ($result['success'] === false) {
            Response::error(
                $result['error_code'],
                $result['message'],
                $result['status_code']
            );
        }

        return [
            'user' => $result['user'],
            'token' => $result['token']
        ];
    }
}