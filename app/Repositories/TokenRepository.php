<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(
        int $userId,
        string $tokenHash,
        string $expiresAt
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO tokens (
                user_id,
                token_hash,
                expires_at,
                revoked_at,
                created_at
            ) VALUES (
                :user_id,
                :token_hash,
                :expires_at,
                NULL,
                NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]);
    }

    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM tokens
            WHERE token_hash = :token_hash
            LIMIT 1
        ");

        $stmt->execute([
            'token_hash' => $tokenHash
        ]);

        $token = $stmt->fetch();

        return $token ?: null;
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        $stmt = $this->db->prepare("
            UPDATE tokens
            SET revoked_at = NOW()
            WHERE token_hash = :token_hash
            AND revoked_at IS NULL
        ");

        $stmt->execute([
            'token_hash' => $tokenHash
        ]);
    }
}