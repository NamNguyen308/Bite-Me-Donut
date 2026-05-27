<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class OrderRepository
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
            FROM orders
        ");

        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function sumRevenue(): float
    {
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(total), 0) AS total_revenue
            FROM orders
            WHERE status IN ('PAID', 'SHIPPING', 'COMPLETED')
        ");

        $row = $stmt->fetch();

        return (float) ($row['total_revenue'] ?? 0);
    }

    public function create(
    int $userId,
    float $total,
    string $shippingName,
    string $shippingPhone,
    string $shippingAddress
): int {
    $stmt = $this->db->prepare("
        INSERT INTO orders (
            user_id,
            total,
            status,
            shipping_name,
            shipping_phone,
            shipping_address,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :total,
            'PENDING',
            :shipping_name,
            :shipping_phone,
            :shipping_address,
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        'user_id' => $userId,
        'total' => $total,
        'shipping_name' => $shippingName,
        'shipping_phone' => $shippingPhone,
        'shipping_address' => $shippingAddress
    ]);

    return (int) $this->db->lastInsertId();
}

    public function createItem(
        int $orderId,
        int $productId,
        int $quantity,
        float $price,
        float $subtotal
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id,
                product_id,
                quantity,
                price,
                subtotal,
                created_at,
                updated_at
            ) VALUES (
                :order_id,
                :product_id,
                :quantity,
                :price,
                :subtotal,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $subtotal
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function getItemsByOrderId(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                oi.id,
                oi.product_id,
                p.name,
                oi.price,
                oi.quantity,
                oi.subtotal
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ");

        $stmt->execute([
            'order_id' => $orderId
        ]);

        return $stmt->fetchAll();
    }

    public function getByUserId(int $userId, int $page = 1, int $limit = 10): array
{
    $offset = ($page - 1) * $limit;

    $stmt = $this->db->prepare("
        SELECT *
        FROM orders
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    return $stmt->fetchAll();
}

public function countByUserId(int $userId): int
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        'user_id' => $userId
    ]);

    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0);
}

    public function getAll(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'status' => $status
        ]);
    }
}