<?php

namespace App\Services;

use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;

class CartService
{
    private CartRepository $cartRepository;
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->cartRepository = new CartRepository();
        $this->productRepository = new ProductRepository();
    }

    public function getCart(int $userId): array
    {
        $cart = $this->cartRepository->findActiveByUserId($userId);

        if (!$cart) {
            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'OK',
                'data' => [
                    'cart' => [
                        'id' => null,
                        'status' => 'ACTIVE',
                        'items' => [],
                        'total' => 0
                    ]
                ]
            ];
        }

        $items = $this->cartRepository->getItemsByCartId((int) $cart['id']);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'cart' => [
                    'id' => (int) $cart['id'],
                    'status' => $cart['status'],
                    'items' => array_map([$this, 'formatCartItem'], $items),
                    'total' => $this->calculateTotal($items)
                ]
            ]
        ];
    }

    public function addToCart(int $userId, int $productId, int $quantity): array
    {
        $errors = [];

        if ($productId <= 0) {
            $errors['product_id'] = 'Product ID is required';
        }

        if ($quantity < 1) {
            $errors['quantity'] = 'Quantity must be greater than or equal to 1';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid request data',
                'errors' => $errors
            ];
        }

        $product = $this->productRepository->findActiveById($productId);

        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product not found'
            ];
        }

        if ((int) $product['stock'] < $quantity) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'INSUFFICIENT_STOCK',
                'message' => 'Product stock is not enough'
            ];
        }

        $cart = $this->cartRepository->findActiveByUserId($userId);

        if (!$cart) {
            $cartId = $this->cartRepository->create($userId);
        } else {
            $cartId = (int) $cart['id'];
        }

        $this->cartRepository->addItem($cartId, $productId, $quantity);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'CART_ITEM_ADDED',
            'data' => [
                'cart_id' => $cartId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]
        ];
    }

    private function formatCartItem(array $item): array
    {
        return [
            'id' => (int) $item['id'],
            'product_id' => (int) $item['product_id'],
            'name' => $item['name'],
            'price' => (float) $item['price'],
            'quantity' => (int) $item['quantity'],
            'subtotal' => (float) $item['subtotal']
        ];
    }

    private function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
        }

        return $total;
    }

    public function updateCartItem(int $userId, int $cartItemId, int $quantity): array
{
    $errors = [];

    if ($cartItemId <= 0) {
        $errors['cart_item_id'] = 'Cart item ID is required';
    }

    if ($quantity < 1) {
        $errors['quantity'] = 'Quantity must be greater than or equal to 1';
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Invalid request data',
            'errors' => $errors
        ];
    }

    $cart = $this->cartRepository->findActiveByUserId($userId);

    if (!$cart) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'CART_NOT_FOUND',
            'message' => 'Cart not found'
        ];
    }

    $item = $this->cartRepository->findItemByCartIdAndItemId(
        (int) $cart['id'],
        $cartItemId
    );

    if (!$item) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'CART_ITEM_NOT_FOUND',
            'message' => 'Cart item not found'
        ];
    }

    $product = $this->productRepository->findActiveById((int) $item['product_id']);

    if (!$product) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'PRODUCT_NOT_FOUND',
            'message' => 'Product not found'
        ];
    }

    if ((int) $product['stock'] < $quantity) {
        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => 'Product stock is not enough'
        ];
    }

    $this->cartRepository->updateItemQuantity(
        $cartItemId,
        $quantity
    );

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'CART_ITEM_UPDATED',
        'data' => [
            'cart_id' => (int) $cart['id'],
            'cart_item_id' => $cartItemId,
            'product_id' => (int) $item['product_id'],
            'quantity' => $quantity
        ]
    ];
}

public function removeCartItem(int $userId, int $cartItemId): array
{
    if ($cartItemId <= 0) {
        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Invalid request data',
            'errors' => [
                'cart_item_id' => 'Cart item ID is required'
            ]
        ];
    }

    $cart = $this->cartRepository->findActiveByUserId($userId);

    if (!$cart) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'CART_NOT_FOUND',
            'message' => 'Cart not found'
        ];
    }

    $item = $this->cartRepository->findItemByCartIdAndItemId(
        (int) $cart['id'],
        $cartItemId
    );

    if (!$item) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'CART_ITEM_NOT_FOUND',
            'message' => 'Cart item not found'
        ];
    }

    $this->cartRepository->removeItem($cartItemId);

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'CART_ITEM_REMOVED',
        'data' => [
            'cart_id' => (int) $cart['id'],
            'cart_item_id' => $cartItemId
        ]
    ];
}

public function clearCart(int $userId): array
{
    $cart = $this->cartRepository->findActiveByUserId($userId);

    if (!$cart) {
        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'CART_CLEARED',
            'data' => [
                'cart_id' => null
            ]
        ];
    }

    $this->cartRepository->clearItems((int) $cart['id']);

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'CART_CLEARED',
        'data' => [
            'cart_id' => (int) $cart['id']
        ]
    ];
}   
}