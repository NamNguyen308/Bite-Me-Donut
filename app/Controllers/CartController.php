<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Services\CartService;

class CartController
{
    private AuthMiddleware $authMiddleware;
    private CartService $cartService;

    public function __construct()
    {
        $this->authMiddleware = new AuthMiddleware();
        $this->cartService = new CartService();
    }

    public function index(Request $request): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $result = $this->cartService->getCart(
            (int) $auth['user']['id']
        );

        $this->sendResult($result);
    }

    public function add(Request $request): void
    {
        $auth = $this->authMiddleware->requireAuth($request);

        $body = $request->body();

        $productId = (int) ($body['product_id'] ?? 0);
        $quantity = (int) ($body['quantity'] ?? 1);

        $result = $this->cartService->addToCart(
            (int) $auth['user']['id'],
            $productId,
            $quantity
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

    public function update(Request $request): void
{
    $auth = $this->authMiddleware->requireAuth($request);

    $body = $request->body();

    $cartItemId = (int) ($body['cart_item_id'] ?? 0);
    $quantity = (int) ($body['quantity'] ?? 0);

    $result = $this->cartService->updateCartItem(
        (int) $auth['user']['id'],
        $cartItemId,
        $quantity
    );

    $this->sendResult($result);
}

public function remove(Request $request): void
{
    $auth = $this->authMiddleware->requireAuth($request);

    $body = $request->body();

    $cartItemId = (int) ($body['cart_item_id'] ?? 0);

    $result = $this->cartService->removeCartItem(
        (int) $auth['user']['id'],
        $cartItemId
    );

    $this->sendResult($result);
}

public function clear(Request $request): void
{
    $auth = $this->authMiddleware->requireAuth($request);

    $result = $this->cartService->clearCart(
        (int) $auth['user']['id']
    );

    $this->sendResult($result);
}
}