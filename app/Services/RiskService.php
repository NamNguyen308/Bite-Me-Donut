<?php

namespace App\Services;

use App\Repositories\RiskLogRepository;

class RiskService
{
    private RiskLogRepository $riskLogRepository;

    public function __construct()
    {
        $this->riskLogRepository = new RiskLogRepository();
    }

    public function logRule(
        ?int $userId,
        ?string $challengeId,
        string $ruleCode,
        string $description,
        int $score,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $this->riskLogRepository->create(
            $userId,
            $challengeId,
            $ruleCode,
            $description,
            $score,
            $ipAddress,
            $userAgent
        );
    }

    public function hasRuleForChallenge(string $challengeId, string $ruleCode): bool
    {
        return $this->riskLogRepository->existsRuleForChallenge($challengeId, $ruleCode);
    }

    public function hasRecentRule(
        string $ruleCode,
        ?int $userId,
        ?string $ipAddress,
        int $minutes = 15
    ): bool {
        return $this->riskLogRepository->existsRecentRuleForUserOrIp(
            $ruleCode,
            $userId,
            $ipAddress,
            $minutes
        );
    }

    public function getTotalScoreByChallengeId(string $challengeId): int
    {
        return $this->riskLogRepository->sumScoreByChallengeId($challengeId);
    }

    public function getRuleCodesByChallengeId(string $challengeId): array
    {
        return $this->riskLogRepository->getRuleCodesByChallengeId($challengeId);
    }

    public function calculateRiskLevel(int $score): string
    {
        if ($score >= 51) {
            return 'HIGH';
        }

        if ($score >= 20) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}