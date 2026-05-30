<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AdminMiddleware;
use App\Services\AdminService;

class AdminController
{
    private AdminMiddleware $adminMiddleware;
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminMiddleware = new AdminMiddleware();
        $this->adminService = new AdminService();
    }

    public function dashboard(Request $request): void
    {
        $this->adminMiddleware->requireAdmin($request);

        $result = $this->adminService->getDashboard();

        $this->sendResult($result);
    }

    public function login(Request $request): void
    {
        $result = $this->adminService->adminLogin($request->body());
        $this->sendResult($result);
    }

    public function getCustomers(Request $request): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $result = $this->adminService->getCustomers($request->query());
        $this->sendResult($result);
    }

    public function createCustomer(Request $request): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $result = $this->adminService->createCustomer($request->body());
        $this->sendResult($result);
    }

    public function deleteCustomer(Request $request, array $params = []): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $id = (int) ($params['id'] ?? 0);
        $result = $this->adminService->deleteCustomer($id);
        $this->sendResult($result);
    }

    public function getOrders(Request $request): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $result = $this->adminService->getOrders($request->query());
        $this->sendResult($result);
    }

    public function createOrder(Request $request): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $result = $this->adminService->createOrder($request->body());
        $this->sendResult($result);
    }

    public function updateOrderStatus(Request $request, array $params = []): void
    {
        $this->adminMiddleware->requireAdmin($request);
        $id = (int) ($params['id'] ?? 0);
        $result = $this->adminService->updateOrderStatus($id, $request->body());
        $this->sendResult($result);
    }

    private function sendResult(array $result): void
{
    if ($result['success'] === true) {
        Response::success(
            $result['message'],
            $result['data'] ?? [],
            $result['status_code']
        );

        return;
    }

    Response::error(
        $result['error_code'],
        $result['message'],
        $result['status_code'],
        $result['errors'] ?? []
    );
}
}