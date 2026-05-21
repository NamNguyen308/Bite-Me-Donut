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
}