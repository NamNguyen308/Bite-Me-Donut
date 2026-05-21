<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Services\OrderService;

class OrderController
{
    private AuthMiddleware $authMiddleware;
    private OrderService $orderService;

    public function __construct()
    {
        $this->authMiddleware = new AuthMiddleware();
        $this->orderService = new OrderService();
    }

    public function index(Request $request): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $result = $this->orderService->getOrdersForRequester(
            (int) $auth['user']['id'],
            $auth['user']['role'],
            $request->query()
        );

        $this->sendResult($result);
    }

    public function store(Request $request): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $result = $this->orderService->createOrderFromCart(
            (int) $auth['user']['id'],
            $request->body()
        );

        $this->sendResult($result);
    }

    public function show(Request $request, array $params = []): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $orderId = (int) ($params['id'] ?? 0);

        $result = $this->orderService->getOrderByIdForRequester(
            (int) $auth['user']['id'],
            $auth['user']['role'],
            $orderId
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