<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class AdminMiddleware
{
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->authMiddleware = new AuthMiddleware();
    }

    public function requireAdmin(Request $request): array
    {
        $auth = $this->authMiddleware->requireAuth($request);

        if (($auth['user']['role'] ?? '') !== 'admin') {
            Response::error(
                'ACCESS_DENIED',
                'Admin permission is required',
                403
            );
        }

        return $auth;
    }
}