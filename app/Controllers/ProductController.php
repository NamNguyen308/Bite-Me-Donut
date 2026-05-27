<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;

class ProductController
{
    private ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function index(Request $request): void
    {
        $result = $this->productService->getProducts(
            $request->query()
        );

        $this->sendResult($result);
    }

    public function show(Request $request, array $params = []): void
    {
        $productId = (int) ($params['id'] ?? 0);

        $result = $this->productService->getProductById($productId);

        $this->sendResult($result);
    }

    public function adminIndex(Request $request): void
    {
        $result = $this->productService->getAllProductsAdmin(
            $request->query()
        );

        $this->sendResult($result);
    }

    public function adminShow(Request $request, array $params = []): void
    {
        $productId = (int) ($params['id'] ?? 0);

        $result = $this->productService->getProductByIdAdmin($productId);

        $this->sendResult($result);
    }

    public function store(Request $request): void
    {
        $result = $this->productService->createProduct($request->body());
        $this->sendResult($result);
    }

    public function update(Request $request, array $params = []): void
    {
        $productId = (int) ($params['id'] ?? 0);
        $result = $this->productService->updateProduct($productId, $request->body());
        $this->sendResult($result);
    }

    public function destroy(Request $request, array $params = []): void
    {
        $productId = (int) ($params['id'] ?? 0);
        $result = $this->productService->deleteProduct($productId);
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