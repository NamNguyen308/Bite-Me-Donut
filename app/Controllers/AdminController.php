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

        Response::success(
            $result['message'],
            $result['data'],
            $result['status_code']
        );
    }
}