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

    public function getAllUsers(int $page = 1, int $limit = 10, ?string $keyword = null): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT id, name, email, phone, role, is_active, created_at, updated_at FROM users WHERE 1=1";
        $params = [];

        if ($keyword !== null && $keyword !== '') {
            $sql .= " AND (name LIKE :keyword OR email LIKE :keyword OR phone LIKE :keyword)";
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                name, email, phone, password_hash, role, is_active, created_at, updated_at
            ) VALUES (
                :name, :email, :phone, :password_hash, :role, :is_active, NOW(), NULL
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'] ?? 'customer',
            'is_active' => $data['is_active'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}