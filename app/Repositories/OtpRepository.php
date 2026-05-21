<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class OtpRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(
        string $challengeId,
        int $userId,
        string $otpHash,
        string $purpose,
        string $expiresAt
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO otps (
                challenge_id,
                user_id,
                otp_hash,
                purpose,
                expires_at,
                is_used,
                used_at,
                created_at
            ) VALUES (
                :challenge_id,
                :user_id,
                :otp_hash,
                :purpose,
                :expires_at,
                0,
                NULL,
                NOW()
            )
        ");

        $stmt->execute([
            'challenge_id' => $challengeId,
            'user_id' => $userId,
            'otp_hash' => $otpHash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt
        ]);
    }
    public function findLatestByChallengeId(string $challengeId): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM otps
        WHERE challenge_id = :challenge_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        'challenge_id' => $challengeId
    ]);

    $otp = $stmt->fetch();

    return $otp ?: null;
}

public function markUsed(int $id): void
{
    $stmt = $this->db->prepare("
        UPDATE otps
        SET is_used = 1,
            used_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);
}
}