<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RiskLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(
        ?int $userId,
        ?string $challengeId,
        string $ruleCode,
        string $description,
        int $score,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO risk_logs (
                user_id,
                challenge_id,
                rule_code,
                description,
                score,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :user_id,
                :challenge_id,
                :rule_code,
                :description,
                :score,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'challenge_id' => $challengeId,
            'rule_code' => $ruleCode,
            'description' => $description,
            'score' => $score,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    public function existsRuleForChallenge(string $challengeId, string $ruleCode): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM risk_logs
            WHERE challenge_id = :challenge_id
            AND rule_code = :rule_code
            LIMIT 1
        ");

        $stmt->execute([
            'challenge_id' => $challengeId,
            'rule_code' => $ruleCode
        ]);

        return (bool) $stmt->fetch();
    }

    public function existsRecentRuleForUserOrIp(
        string $ruleCode,
        ?int $userId,
        ?string $ipAddress,
        int $minutes = 15
    ): bool {
        $minutes = max(1, $minutes);

        $conditions = [];
        $params = [
            'rule_code' => $ruleCode
        ];

        if ($userId !== null) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $conditions[] = 'ip_address = :ip_address';
            $params['ip_address'] = $ipAddress;
        }

        if (empty($conditions)) {
            return false;
        }

        $where = implode(' OR ', $conditions);

        $stmt = $this->db->prepare("
            SELECT id
            FROM risk_logs
            WHERE rule_code = :rule_code
            AND created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)
            AND ({$where})
            LIMIT 1
        ");

        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function sumScoreByChallengeId(string $challengeId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(score), 0) AS total_score
            FROM risk_logs
            WHERE challenge_id = :challenge_id
        ");

        $stmt->execute([
            'challenge_id' => $challengeId
        ]);

        $row = $stmt->fetch();

        return (int) ($row['total_score'] ?? 0);
    }

    public function getRuleCodesByChallengeId(string $challengeId): array
    {
        $stmt = $this->db->prepare("
            SELECT rule_code
            FROM risk_logs
            WHERE challenge_id = :challenge_id
        ");

        $stmt->execute([
            'challenge_id' => $challengeId
        ]);

        return array_column($stmt->fetchAll(), 'rule_code');
    }
    public function countToday(): int
{
    $stmt = $this->db->query("
        SELECT COUNT(*) AS total
        FROM risk_logs
        WHERE DATE(created_at) = CURDATE()
    ");

    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0);
}
}