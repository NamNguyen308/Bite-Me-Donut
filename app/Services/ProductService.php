<?php

namespace App\Services;

use App\Repositories\ProductRepository;

class ProductService
{
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
    }

    public function getProducts(array $query): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 10);
        $keyword = trim($query['keyword'] ?? '');

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

        $products = $this->productRepository->getActiveProducts(
            $page,
            $limit,
            $keyword !== '' ? $keyword : null
        );

        $total = $this->productRepository->countActive(
            $keyword !== '' ? $keyword : null
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'products' => array_map([$this, 'formatProduct'], $products),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ]
            ]
        ];
    }

    public function getProductById(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid request data',
                'errors' => [
                    'id' => 'Product ID must be numeric'
                ]
            ];
        }

        $product = $this->productRepository->findActiveById($id);

        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product not found'
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'product' => $this->formatProduct($product)
            ]
        ];
    }

    private function formatProduct(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'short_description' => $product['short_description'],
            'description' => $product['description'],
            'ingredient' => $product['ingredient'],
            'price' => (float) $product['price'],
            'stock' => (int) $product['stock'],
            'image' => $product['image'],
            'is_active' => (int) $product['is_active'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }

    public function getAllProductsAdmin(array $query): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 100);
        $keyword = trim($query['keyword'] ?? '');

        $products = $this->productRepository->getAllProducts(
            $page,
            $limit,
            $keyword !== '' ? $keyword : null
        );

        $total = $this->productRepository->countAll();

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'products' => array_map([$this, 'formatProduct'], $products),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ]
            ]
        ];
    }

    public function getProductByIdAdmin(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid product ID'
            ];
        }

        $product = $this->productRepository->findById($id);

        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product not found'
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'product' => $this->formatProduct($product)
            ]
        ];
    }

    public function createProduct(array $data): array
    {
        if (empty($data['name']) || empty($data['price'])) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Name and price are required'
            ];
        }

        $productId = $this->productRepository->create($data);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Product created',
            'data' => [
                'id' => $productId
            ]
        ];
    }

    public function updateProduct(int $id, array $data): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid product ID'
            ];
        }

        $product = $this->productRepository->findById($id);
        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product not found'
            ];
        }

        $this->productRepository->update($id, $data);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Product updated',
            'data' => []
        ];
    }

    public function deleteProduct(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid product ID'
            ];
        }

        $product = $this->productRepository->findById($id);
        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product not found'
            ];
        }

        $this->productRepository->delete($id);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Product deleted',
            'data' => []
        ];
    }
}