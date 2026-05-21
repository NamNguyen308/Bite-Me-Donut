<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class LoginChallengeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(
        string $id,
        int $userId,
        string $status,
        string $expiresAt,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO login_challenges (
                id,
                user_id,
                status,
                otp_send_count,
                otp_wrong_count,
                risk_score,
                risk_level,
                password_verified_at,
                expires_at,
                ip_address,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :user_id,
                :status,
                0,
                0,
                0,
                'LOW',
                NOW(),
                :expires_at,
                :ip_address,
                :user_agent,
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'status' => $status,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM login_challenges
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $challenge = $stmt->fetch();

        return $challenge ?: null;
    }
    public function incrementOtpSendCount(string $id): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET otp_send_count = otp_send_count + 1,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);
}

public function updateStatus(string $id, string $status): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET status = :status,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'status' => $status
    ]);
}

public function blockChallenge(string $id, string $blockedUntil): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET status = 'BLOCKED',
            blocked_until = :blocked_until,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'blocked_until' => $blockedUntil
    ]);
}

public function incrementOtpWrongCount(string $id): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET otp_wrong_count = otp_wrong_count + 1,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);
}

public function markOtpVerified(string $id): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET status = 'OTP_VERIFIED',
            otp_verified_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);
}

public function updateRisk(
    string $id,
    int $riskScore,
    string $riskLevel
): void {
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET risk_score = :risk_score,
            risk_level = :risk_level,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'risk_score' => $riskScore,
        'risk_level' => $riskLevel
    ]);
}

public function markAuthenticated(string $id): void
{
    $stmt = $this->db->prepare("
        UPDATE login_challenges
        SET status = 'AUTHENTICATED',
            authenticated_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);
}
}