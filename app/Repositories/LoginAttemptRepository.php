<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class LoginAttemptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(
        ?int $userId,
        ?string $phone,
        ?string $email,
        ?string $ipAddress,
        ?string $userAgent,
        string $status,
        ?string $reason
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (
                user_id,
                phone,
                email,
                ip_address,
                user_agent,
                status,
                reason,
                created_at
            ) VALUES (
                :user_id,
                :phone,
                :email,
                :ip_address,
                :user_agent,
                :status,
                :reason,
                NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'phone' => $phone,
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => $status,
            'reason' => $reason
        ]);
    }

    public function countRecentFailedAttempts(
    ?int $userId,
    ?string $phone,
    ?string $email,
    ?string $ipAddress,
    int $minutes = 15
): int {
    $minutes = max(1, $minutes);

    $conditions = [];
    $params = [];

    if ($userId !== null) {
        $conditions[] = 'user_id = :user_id';
        $params['user_id'] = $userId;
    }

    if ($phone !== null && $phone !== '') {
        $conditions[] = 'phone = :phone';
        $params['phone'] = $phone;
    }

    if ($email !== null && $email !== '') {
        $conditions[] = 'email = :email';
        $params['email'] = $email;
    }

    if ($ipAddress !== null && $ipAddress !== '') {
        $conditions[] = 'ip_address = :ip_address';
        $params['ip_address'] = $ipAddress;
    }

    if (empty($conditions)) {
        return 0;
    }

    $where = implode(' OR ', $conditions);

    $stmt = $this->db->prepare("
        SELECT COUNT(*) AS total
        FROM login_attempts
        WHERE status = 'FAILED'
        AND created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)
        AND ({$where})
    ");

    $stmt->execute($params);

    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0);
}
}