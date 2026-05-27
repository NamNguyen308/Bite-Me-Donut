<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\ProductRepository;
use App\Repositories\OrderRepository;
use App\Repositories\RiskLogRepository;

class AdminService
{
    private UserRepository $userRepository;
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;
    private RiskLogRepository $riskLogRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->productRepository = new ProductRepository();
        $this->orderRepository = new OrderRepository();
        $this->riskLogRepository = new RiskLogRepository();
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
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Email and password are required'
            ];
        }

        $user = $this->userRepository->findByPhoneOrEmail(null, $email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'status_code' => 401,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid email or password'
            ];
        }

        if ($user['role'] !== 'admin') {
            return [
                'success' => false,
                'status_code' => 403,
                'error_code' => 'ACCESS_DENIED',
                'message' => 'You do not have admin permissions'
            ];
        }

        $tokenService = new TokenService();
        $token = $tokenService->createAccessToken((int) $user['id']);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token['plain_token'],
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]
        ];
    }

    public function getCustomers(array $query): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 50);
        $keyword = trim($query['keyword'] ?? '');

        $users = $this->userRepository->getAllUsers($page, $limit, $keyword !== '' ? $keyword : null);
        $total = $this->userRepository->countAll();

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'customers' => array_map(function($user) {
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
        if (empty($data['phone']) || empty($data['password'])) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Phone and password are required'
            ];
        }
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $userId = $this->userRepository->create($data);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Customer created',
            'data' => ['id' => $userId]
        ];
    }

    public function deleteCustomer(int $id): array
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'NOT_FOUND',
                'message' => 'User not found'
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
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 50);

        $orders = $this->orderRepository->getAll($page, $limit);
        $total = $this->orderRepository->countAll();
        
        foreach ($orders as &$order) {
            $order['items'] = $this->orderRepository->getItemsByOrderId((int)$order['id']);
        }

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
        // Simple order creation for admin panel
        $userId = $data['user_id'] ?? 0;
        $total = $data['total'] ?? 0;
        
        $orderId = $this->orderRepository->create(
            $userId,
            $total,
            $data['shipping_name'] ?? '',
            $data['shipping_phone'] ?? '',
            $data['shipping_address'] ?? ''
        );
        
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->orderRepository->createItem(
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                );
            }
        }

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Order created',
            'data' => ['id' => $orderId]
        ];
    }

    public function updateOrderStatus(int $id, array $data): array
    {
        $status = $data['status'] ?? '';
        if (empty($status)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Status is required'
            ];
        }

        $order = $this->orderRepository->findById($id);
        if (!$order) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'NOT_FOUND',
                'message' => 'Order not found'
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