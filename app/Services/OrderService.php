<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Throwable;

class OrderService
{
    private CartRepository $cartRepository;
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->cartRepository = new CartRepository();
        $this->orderRepository = new OrderRepository();
        $this->productRepository = new ProductRepository();
    }

    public function createOrderFromCart(int $userId, array $data): array
    {
        $shippingName = trim($data['shipping_name'] ?? '');
        $shippingPhone = trim($data['shipping_phone'] ?? '');
        $shippingAddress = trim($data['shipping_address'] ?? '');

        $errors = [];

        if ($shippingName === '') {
            $errors['shipping_name'] = 'Shipping name is required';
        }

        if ($shippingPhone === '') {
            $errors['shipping_phone'] = 'Shipping phone is required';
        }

        if ($shippingAddress === '') {
            $errors['shipping_address'] = 'Shipping address is required';
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

        $db = Database::getConnection();

        $cart = $this->cartRepository->findActiveByUserId($userId);

        if (!$cart) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'CART_EMPTY',
                'message' => 'Cart is empty'
            ];
        }

        $items = $this->cartRepository->getItemsByCartId((int) $cart['id']);

        if (empty($items)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'CART_EMPTY',
                'message' => 'Cart is empty'
            ];
        }

        foreach ($items as $item) {
            $product = $this->productRepository->findActiveById((int) $item['product_id']);

            if (!$product) {
                return [
                    'success' => false,
                    'status_code' => 404,
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'message' => 'Product not found'
                ];
            }

            if ((int) $product['stock'] < (int) $item['quantity']) {
                return [
                    'success' => false,
                    'status_code' => 400,
                    'error_code' => 'INSUFFICIENT_STOCK',
                    'message' => 'Product stock is not enough'
                ];
            }
        }

        $total = $this->calculateTotal($items);

        try {
            $db->beginTransaction();

            $orderId = $this->orderRepository->create(
                $userId,
                $total,
                $shippingName,
                $shippingPhone,
                $shippingAddress
            );

            foreach ($items as $item) {
                $price = (float) $item['price'];
                $quantity = (int) $item['quantity'];
                $subtotal = $price * $quantity;

                $this->orderRepository->createItem(
                    $orderId,
                    (int) $item['product_id'],
                    $quantity,
                    $price,
                    $subtotal
                );

                $this->productRepository->decreaseStock(
                    (int) $item['product_id'],
                    $quantity
                );
            }

            $this->cartRepository->markCompleted((int) $cart['id']);

            $db->commit();

            $order = $this->orderRepository->findById($orderId);
            $orderItems = $this->orderRepository->getItemsByOrderId($orderId);

            return [
                'success' => true,
                'status_code' => 201,
                'message' => 'ORDER_CREATED',
                'data' => [
                    'order' => [
                        'id' => (int) $order['id'],
                        'user_id' => (int) $order['user_id'],
                        'total' => (float) $order['total'],
                        'status' => $order['status'],
                        'shipping_name' => $order['shipping_name'],
                        'shipping_phone' => $order['shipping_phone'],
                        'shipping_address' => $order['shipping_address'],
                        'items' => array_map([$this, 'formatOrderItem'], $orderItems)
                    ]
                ]
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return [
                'success' => false,
                'status_code' => 500,
                'error_code' => 'ORDER_CREATE_FAILED',
                'message' => 'Order could not be created',
                'errors' => [
                    'detail' => $e->getMessage()
                ]
            ];
        }
    }

    private function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
        }

        return $total;
    }

    private function formatOrderItem(array $item): array
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

    public function getOrdersForRequester(
    int $requesterId,
    string $requesterRole,
    array $query
): array {
    $page = (int) ($query['page'] ?? 1);
    $limit = (int) ($query['limit'] ?? 10);

    $errors = [];

    if ($page < 1) {
        $errors['page'] = 'Page must be greater than or equal to 1';
    }

    if ($limit < 1 || $limit > 100) {
        $errors['limit'] = 'Limit must be between 1 and 100';
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

    if ($requesterRole === 'admin') {
        $orders = $this->orderRepository->getAll($page, $limit);
        $total = $this->orderRepository->countAll();
    } else {
        $orders = $this->orderRepository->getByUserId($requesterId, $page, $limit);
        $total = $this->orderRepository->countByUserId($requesterId);
    }

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'OK',
        'data' => [
            'orders' => array_map([$this, 'formatOrder'], $orders),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ]
    ];
}

public function getOrderByIdForRequester(
    int $requesterId,
    string $requesterRole,
    int $orderId
): array {
    if ($orderId <= 0) {
        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Invalid request data',
            'errors' => [
                'id' => 'Order ID must be numeric'
            ]
        ];
    }

    $order = $this->orderRepository->findById($orderId);

    if (!$order) {
        return [
            'success' => false,
            'status_code' => 404,
            'error_code' => 'ORDER_NOT_FOUND',
            'message' => 'Order not found'
        ];
    }

    if ($requesterRole !== 'admin' && (int) $order['user_id'] !== $requesterId) {
        return [
            'success' => false,
            'status_code' => 403,
            'error_code' => 'IDOR_BLOCKED',
            'message' => "You are not allowed to access another user's order"
        ];
    }

    $items = $this->orderRepository->getItemsByOrderId($orderId);

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'OK',
        'data' => [
            'order' => array_merge(
                $this->formatOrder($order),
                [
                    'items' => array_map([$this, 'formatOrderItem'], $items)
                ]
            )
        ]
    ];
}

private function formatOrder(array $order): array
{
    return [
        'id' => (int) $order['id'],
        'user_id' => (int) $order['user_id'],
        'total' => (float) $order['total'],
        'status' => $order['status'],
        'shipping_name' => $order['shipping_name'],
        'shipping_phone' => $order['shipping_phone'],
        'shipping_address' => $order['shipping_address'],
        'payment_method' => $order['payment_method'] ?? 'COD',
        'created_at' => $order['created_at']
    ];
}
}