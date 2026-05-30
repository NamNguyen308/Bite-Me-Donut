<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\ProductRepository;
use App\Repositories\OrderRepository;
use App\Repositories\RiskLogRepository;
use App\Services\TokenService;
use Throwable;

class AdminService
{
    private UserRepository $userRepository;
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;
    private RiskLogRepository $riskLogRepository;
    private TokenService $tokenService;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->productRepository = new ProductRepository();
        $this->orderRepository = new OrderRepository();
        $this->riskLogRepository = new RiskLogRepository();
        $this->tokenService = new TokenService();
    }

    public function getDashboard(): array
    {
        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'dashboard' => [
                    'total_users' => $this->userRepository->countAll(),
                    'total_products' => $this->productRepository->countAll(),
                    'total_orders' => $this->orderRepository->countAll(),
                    'total_revenue' => $this->orderRepository->sumRevenue(),
                    'risk_events_today' => $this->riskLogRepository->countToday()
                ]
            ]
        ];
    }

    public function adminLogin(array $data): array
    {
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'EMAIL_REQUIRED',
                'message' => 'Email is required',
                'errors' => [
                    'email' => 'Email is required'
                ]
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'INVALID_EMAIL',
                'message' => 'Invalid email format',
                'errors' => [
                    'email' => 'Invalid email format'
                ]
            ];
        }

        if ($password === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'PASSWORD_REQUIRED',
                'message' => 'Password is required',
                'errors' => [
                    'password' => 'Password is required'
                ]
            ];
        }

        try {
            /*
             * Admin login bypasses OTP completely.
             * Flow:
             * email + password valid
             * role = admin
             * is_active = 1
             * create access token immediately
             */
            $user = $this->userRepository->findByPhoneOrEmail(null, $email);

            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                return [
                    'success' => false,
                    'status_code' => 401,
                    'error_code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid email or password',
                    'errors' => []
                ];
            }

            if ((string)$user['role'] !== 'admin') {
                return [
                    'success' => false,
                    'status_code' => 403,
                    'error_code' => 'ADMIN_ONLY',
                    'message' => 'This account is not an admin account',
                    'errors' => []
                ];
            }

            if ((int)$user['is_active'] !== 1) {
                return [
                    'success' => false,
                    'status_code' => 403,
                    'error_code' => 'ACCOUNT_INACTIVE',
                    'message' => 'Admin account is inactive',
                    'errors' => []
                ];
            }

            $token = $this->tokenService->createAccessToken((int)$user['id']);

            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'ADMIN_LOGIN_SUCCESS',
                'data' => [
                    'access_token' => $token['plain_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => $token['expires_in'] ?? null,
                    'expires_at' => $token['expires_at'] ?? null,
                    'user' => [
                        'id' => (int)$user['id'],
                        'name' => $user['name'] ?? null,
                        'email' => $user['email'] ?? null,
                        'phone' => $user['phone'] ?? null,
                        'role' => $user['role'],
                        'is_active' => (int)$user['is_active'],
                        'created_at' => $user['created_at'] ?? null,
                        'updated_at' => $user['updated_at'] ?? null
                    ]
                ]
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error_code' => 'ADMIN_LOGIN_FAILED',
                'message' => 'Cannot login admin',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    public function getCustomers(array $query): array
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = max(1, min(100, (int)($query['limit'] ?? 50)));
        $keyword = trim((string)($query['keyword'] ?? ''));

        $users = $this->userRepository->getAllUsers(
            $page,
            $limit,
            $keyword !== '' ? $keyword : null
        );

        $total = $this->userRepository->countAll();

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'customers' => array_map(function (array $user): array {
                    unset($user['password_hash']);
                    return $user;
                }, $users),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ]
            ]
        ];
    }

    public function createCustomer(array $data): array
    {
        $phone = trim((string)($data['phone'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($phone === '' || $password === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Phone and password are required',
                'errors' => [
                    'phone' => $phone === '' ? 'Phone is required' : null,
                    'password' => $password === '' ? 'Password is required' : null
                ]
            ];
        }

        if (strlen($password) < 6) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'PASSWORD_TOO_SHORT',
                'message' => 'Password must be at least 6 characters',
                'errors' => [
                    'password' => 'Password must be at least 6 characters'
                ]
            ];
        }

        try {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            unset($data['password']);

            $data['role'] = $data['role'] ?? 'customer';
            $data['is_active'] = $data['is_active'] ?? 1;

            $userId = $this->userRepository->create($data);

            return [
                'success' => true,
                'status_code' => 201,
                'message' => 'Customer created',
                'data' => [
                    'id' => $userId
                ]
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error_code' => 'CUSTOMER_CREATE_FAILED',
                'message' => 'Cannot create customer',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    public function deleteCustomer(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'INVALID_ID',
                'message' => 'Invalid user id',
                'errors' => []
            ];
        }

        $user = $this->userRepository->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'NOT_FOUND',
                'message' => 'User not found',
                'errors' => []
            ];
        }

        if (($user['role'] ?? '') === 'admin') {
            return [
                'success' => false,
                'status_code' => 403,
                'error_code' => 'CANNOT_DELETE_ADMIN',
                'message' => 'Admin accounts cannot be deleted from customer management',
                'errors' => []
            ];
        }

        $this->userRepository->delete($id);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Customer deleted',
            'data' => []
        ];
    }

    public function getOrders(array $query): array
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = max(1, min(100, (int)($query['limit'] ?? 50)));

        $orders = $this->orderRepository->getAll($page, $limit);
        $total = $this->orderRepository->countAll();

        foreach ($orders as &$order) {
            $order['items'] = $this->orderRepository->getItemsByOrderId((int)$order['id']);
        }

        unset($order);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'orders' => $orders,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ]
            ]
        ];
    }

    public function createOrder(array $data): array
    {
        $userId = (int)($data['user_id'] ?? 0);
        $total = (float)($data['total'] ?? 0);
        $items = $data['items'] ?? [];

        if ($userId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'USER_ID_REQUIRED',
                'message' => 'User id is required',
                'errors' => [
                    'user_id' => 'User id is required'
                ]
            ];
        }

        if (!is_array($items) || count($items) === 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'ORDER_ITEMS_REQUIRED',
                'message' => 'Order items are required',
                'errors' => [
                    'items' => 'Order items are required'
                ]
            ];
        }

        try {
            if ($total <= 0) {
                foreach ($items as $item) {
                    $price = (float)($item['price'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 0);
                    $total += $price * $quantity;
                }
            }

            $orderId = $this->orderRepository->create(
                $userId,
                $total,
                (string)($data['shipping_name'] ?? ''),
                (string)($data['shipping_phone'] ?? ''),
                (string)($data['shipping_address'] ?? '')
            );

            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $price = (float)($item['price'] ?? 0);

                if ($productId <= 0 || $quantity <= 0 || $price < 0) {
                    continue;
                }

                $this->orderRepository->createItem(
                    $orderId,
                    $productId,
                    $quantity,
                    $price,
                    $price * $quantity
                );
            }

            return [
                'success' => true,
                'status_code' => 201,
                'message' => 'Order created',
                'data' => [
                    'id' => $orderId
                ]
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status_code' => 500,
                'error_code' => 'ORDER_CREATE_FAILED',
                'message' => 'Cannot create order',
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    public function updateOrderStatus(int $id, array $data): array
    {
        $status = strtoupper(trim((string)($data['status'] ?? '')));

        $allowedStatuses = [
            'PENDING',
            'PROCESSING',
            'SHIPPING',
            'COMPLETED',
            'CANCELLED'
        ];

        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'INVALID_ORDER_ID',
                'message' => 'Invalid order id',
                'errors' => []
            ];
        }

        if ($status === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Status is required',
                'errors' => [
                    'status' => 'Status is required'
                ]
            ];
        }

        if (!in_array($status, $allowedStatuses, true)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'INVALID_STATUS',
                'message' => 'Invalid order status',
                'errors' => [
                    'status' => 'Allowed values: ' . implode(', ', $allowedStatuses)
                ]
            ];
        }

        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'NOT_FOUND',
                'message' => 'Order not found',
                'errors' => []
            ];
        }

        $this->orderRepository->updateStatus($id, $status);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Order status updated',
            'data' => []
        ];
    }
}