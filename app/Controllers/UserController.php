<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Services\UserService;

class UserController
{
    private AuthMiddleware $authMiddleware;
    private UserService $userService;

    public function __construct()
    {
        $this->authMiddleware = new AuthMiddleware();
        $this->userService = new UserService();
    }

    public function me(Request $request): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $result = $this->userService->getCurrentUser(
            (int) $auth['user']['id']
        );

        $this->sendResult($result);
    }

    public function show(Request $request, array $params = []): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $targetUserId = (int) ($params['id'] ?? 0);

        $result = $this->userService->getUserByIdForRequester(
            (int) $auth['user']['id'],
            $auth['user']['role'],
            $targetUserId
        );

        $this->sendResult($result);
    }

    private function sendResult(array $result): void
    {
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