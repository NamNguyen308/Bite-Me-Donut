<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ProductRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function countAll(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) AS total
            FROM products
        ");

        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function countActive(?string $keyword = null): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM products
            WHERE is_active = 1
        ";

        $params = [];

        if ($keyword !== null && $keyword !== '') {
            $sql .= " AND name LIKE :keyword";
            $params['keyword'] = '%' . $keyword . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function getActiveProducts(int $page = 1, int $limit = 10, ?string $keyword = null): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT
                id,
                name,
                description,
                price,
                stock,
                image,
                is_active,
                created_at,
                updated_at
            FROM products
            WHERE is_active = 1
        ";

        $params = [];

        if ($keyword !== null && $keyword !== '') {
            $sql .= " AND name LIKE :keyword";
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= "
            ORDER BY id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                description,
                price,
                stock,
                image,
                is_active,
                created_at,
                updated_at
            FROM products
            WHERE id = :id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function decreaseStock(int $productId, int $quantity): void
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET stock = stock - :decrease_quantity,
                updated_at = NOW()
            WHERE id = :id
            AND stock >= :required_quantity
        ");

        $stmt->execute([
            'id' => $productId,
            'decrease_quantity' => $quantity,
            'required_quantity' => $quantity
        ]);
    }

    public function getAllProducts(int $page = 1, int $limit = 10, ?string $keyword = null): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT
                id,
                name,
                short_description,
                description,
                ingredient,
                price,
                stock,
                image,
                is_active,
                created_at,
                updated_at
            FROM products
            WHERE 1=1
        ";

        $params = [];

        if ($keyword !== null && $keyword !== '') {
            $sql .= " AND name LIKE :keyword";
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= "
            ORDER BY id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                short_description,
                description,
                ingredient,
                price,
                stock,
                image,
                is_active,
                created_at,
                updated_at
            FROM products
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                name, short_description, description, ingredient,
                price, stock, image, is_active, created_at, updated_at
            ) VALUES (
                :name, :short_description, :description, :ingredient,
                :price, :stock, :image, :is_active, NOW(), NULL
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'ingredient' => $data['ingredient'] ?? null,
            'price' => $data['price'],
            'stock' => $data['stock'] ?? 0,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET name = :name,
                short_description = :short_description,
                description = :description,
                ingredient = :ingredient,
                price = :price,
                stock = :stock,
                image = :image,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'ingredient' => $data['ingredient'] ?? null,
            'price' => $data['price'],
            'stock' => $data['stock'] ?? 0,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}