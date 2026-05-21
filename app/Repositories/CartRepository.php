<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CartRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findActiveByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM carts
            WHERE user_id = :user_id
            AND status = 'ACTIVE'
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $cart = $stmt->fetch();

        return $cart ?: null;
    }

    public function create(int $userId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO carts (
                user_id,
                status,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                'ACTIVE',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findItem(int $cartId, int $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM cart_items
            WHERE cart_id = :cart_id
            AND product_id = :product_id
            LIMIT 1
        ");

        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId
        ]);

        $item = $stmt->fetch();

        return $item ?: null;
    }

    public function addItem(int $cartId, int $productId, int $quantity): void
    {
        $existingItem = $this->findItem($cartId, $productId);

        if ($existingItem) {
            $stmt = $this->db->prepare("
                UPDATE cart_items
                SET quantity = quantity + :quantity,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $existingItem['id'],
                'quantity' => $quantity
            ]);

            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO cart_items (
                cart_id,
                product_id,
                quantity,
                created_at,
                updated_at
            ) VALUES (
                :cart_id,
                :product_id,
                :quantity,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
    }

    public function getItemsByCartId(int $cartId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ci.id,
                ci.product_id,
                p.name,
                p.price,
                ci.quantity,
                (p.price * ci.quantity) AS subtotal
            FROM cart_items ci
            INNER JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = :cart_id
            ORDER BY ci.id ASC
        ");

        $stmt->execute([
            'cart_id' => $cartId
        ]);

        return $stmt->fetchAll();
    }
    public function markCompleted(int $cartId): void
{
    $stmt = $this->db->prepare("
        UPDATE carts
        SET status = 'COMPLETED',
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $cartId
    ]);
}

    public function findItemByCartIdAndItemId(int $cartId, int $itemId): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM cart_items
        WHERE id = :id
        AND cart_id = :cart_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $itemId,
        'cart_id' => $cartId
    ]);

    $item = $stmt->fetch();

    return $item ?: null;
}

public function updateItemQuantity(int $itemId, int $quantity): void
{
    $stmt = $this->db->prepare("
        UPDATE cart_items
        SET quantity = :quantity,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $itemId,
        'quantity' => $quantity
    ]);
}

public function removeItem(int $itemId): void
{
    $stmt = $this->db->prepare("
        DELETE FROM cart_items
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $itemId
    ]);
}

public function clearItems(int $cartId): void
{
    $stmt = $this->db->prepare("
        DELETE FROM cart_items
        WHERE cart_id = :cart_id
    ");

    $stmt->execute([
        'cart_id' => $cartId
    ]);
}
}