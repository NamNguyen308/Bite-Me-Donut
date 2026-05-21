<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login(
            $request->body(),
            $request->ip(),
            $request->userAgent()
        );

        if ($result['success'] === true) {
            Response::success(
                $result['message'],
                $result['data'],
                $result['status_code']
            );
        }

        Response::error(
            $result['error_code'],
            $result['message'],
            $result['status_code'],
            $result['errors'] ?? []
        );
    }

    public function completeLogin(Request $request): void
{
    $result = $this->authService->completeLogin(
        $request->body()
    );

    if ($result['success'] === true) {
        Response::success(
            $result['message'],
            $result['data'],
            $result['status_code']
        );
    }

    Response::error(
        $result['error_code'],
        $result['message'],
        $result['status_code'],
        $result['errors'] ?? []
    );
}

public function logout(Request $request): void
{
    $result = $this->authService->logout(
        $request->bearerToken()
    );

    if ($result['success'] === true) {
        Response::success(
            $result['message'],
            $result['data'],
            $result['status_code']
        );
    }

    Response::error(
        $result['error_code'],
        $result['message'],
        $result['status_code'],
        $result['errors'] ?? []
    );
}
}