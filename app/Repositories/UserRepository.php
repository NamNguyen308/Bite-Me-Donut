<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByPhoneOrEmail(?string $phone, ?string $email): ?array
    {
        if ($phone !== null && $phone !== '') {
            $stmt = $this->db->prepare("
                SELECT *
                FROM users
                WHERE phone = :phone
                LIMIT 1
            ");

            $stmt->execute([
                'phone' => $phone
            ]);

            $user = $stmt->fetch();

            return $user ?: null;
        }

        if ($email !== null && $email !== '') {
            $stmt = $this->db->prepare("
                SELECT *
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch();

            return $user ?: null;
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }
    public function findPublicById(int $id): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            is_active,
            created_at,
            updated_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

public function countAll(): int
{
    $stmt = $this->db->query("
        SELECT COUNT(*) AS total
        FROM users
    ");

    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0);
}

/**
 * Tìm user theo cả email VÀ phone (dùng cho verify identity khi quên mật khẩu).
 */
public function findByEmailAndPhone(string $email, string $phone): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM users
        WHERE email = :email AND phone = :phone
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email,
        'phone' => $phone,
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Cập nhật password_hash cho user (dùng cho reset password).
 */
public function updatePasswordHash(int $userId, string $passwordHash): bool
{
    $stmt = $this->db->prepare("
        UPDATE users
        SET password_hash = :password_hash
        WHERE id = :id
    ");

    return $stmt->execute([
        'password_hash' => $passwordHash,
        'id'            => $userId,
    ]);
}
}