<?php

namespace App\Services;

use App\Core\Config;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\LoginChallengeRepository;

class RuleEngineService
{
    private LoginAttemptRepository $loginAttemptRepository;
    private LoginChallengeRepository $loginChallengeRepository;
    private RiskService $riskService;

    public function __construct()
    {
        $this->loginAttemptRepository = new LoginAttemptRepository();
        $this->loginChallengeRepository = new LoginChallengeRepository();
        $this->riskService = new RiskService();
    }

    /*
     * Sai password xảy ra trước khi có login_challenge.
     * Vì vậy R3 không ghi trực tiếp tại thời điểm password sai.
     * R3 sẽ được attach vào challenge mới sau khi user nhập đúng password.
     */
    public function evaluateFailedPassword(
        ?int $userId,
        ?string $phone,
        ?string $email,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        return;
    }

    public function attachRecentFailedPasswordRiskToChallenge(
        array $challenge,
        ?string $phone,
        ?string $email,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $threshold = (int) Config::get('LOGIN_MAX_FAILED_ATTEMPTS', 3);

        $failedCount = $this->loginAttemptRepository->countRecentFailedAttempts(
            (int) $challenge['user_id'],
            $phone,
            $email,
            $ipAddress,
            15
        );

        if ($failedCount >= $threshold) {
            $this->addRuleIfNotExists(
                $challenge,
                'R3',
                'Failed password attempts exceeded threshold',
                10,
                $ipAddress,
                $userAgent
            );
        }

        return $this->syncChallengeRisk($challenge['id']);
    }

    public function evaluateOtpRequestLimit(
        array $challenge,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        /*
         * R2: Request OTP > 2 lần.
         * Nghĩa là từ lần request thứ 3 trở đi sẽ kích hoạt R2.
         */
        if ((int) $challenge['otp_send_count'] > 2) {
            $this->addRuleIfNotExists(
                $challenge,
                'R2',
                'OTP request limit exceeded',
                30,
                $ipAddress,
                $userAgent
            );
        }

        return $this->syncChallengeRisk($challenge['id']);
    }

    public function evaluateInvalidOtp(
        array $challenge,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        /*
         * R1: Nhập sai OTP > 1 lần.
         * Nghĩa là từ lần sai thứ 2 trở đi sẽ kích hoạt R1.
         */
        if ((int) $challenge['otp_wrong_count'] > 1) {
            $this->addRuleIfNotExists(
                $challenge,
                'R1',
                'Invalid OTP attempts exceeded threshold',
                10,
                $ipAddress,
                $userAgent
            );
        }

        return $this->syncChallengeRisk($challenge['id']);
    }

    private function addRuleIfNotExists(
        array $challenge,
        string $ruleCode,
        string $description,
        int $score,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        if ($this->riskService->hasRuleForChallenge($challenge['id'], $ruleCode)) {
            return;
        }

        $this->riskService->logRule(
            (int) $challenge['user_id'],
            $challenge['id'],
            $ruleCode,
            $description,
            $score,
            $ipAddress,
            $userAgent
        );
    }

    private function evaluateCombinationRules(string $challengeId): void
    {
        $rules = $this->riskService->getRuleCodesByChallengeId($challengeId);

        $hasR1 = in_array('R1', $rules, true);
        $hasR2 = in_array('R2', $rules, true);
        $hasR3 = in_array('R3', $rules, true);

        $challenge = $this->loginChallengeRepository->findById($challengeId);

        if (!$challenge) {
            return;
        }

        if ($hasR1 && $hasR2 && !$this->riskService->hasRuleForChallenge($challengeId, 'C1')) {
            $this->riskService->logRule(
                (int) $challenge['user_id'],
                $challengeId,
                'C1',
                'Combination rule R1 + R2 triggered',
                30,
                $challenge['ip_address'] ?? null,
                $challenge['user_agent'] ?? null
            );
        }

        if ($hasR1 && $hasR3 && !$this->riskService->hasRuleForChallenge($challengeId, 'C2')) {
            $this->riskService->logRule(
                (int) $challenge['user_id'],
                $challengeId,
                'C2',
                'Combination rule R1 + R3 triggered',
                10,
                $challenge['ip_address'] ?? null,
                $challenge['user_agent'] ?? null
            );
        }

        if ($hasR2 && $hasR3 && !$this->riskService->hasRuleForChallenge($challengeId, 'C3')) {
            $this->riskService->logRule(
                (int) $challenge['user_id'],
                $challengeId,
                'C3',
                'Combination rule R2 + R3 triggered',
                10,
                $challenge['ip_address'] ?? null,
                $challenge['user_agent'] ?? null
            );
        }

        if ($hasR1 && $hasR2 && $hasR3 && !$this->riskService->hasRuleForChallenge($challengeId, 'C4')) {
            $this->riskService->logRule(
                (int) $challenge['user_id'],
                $challengeId,
                'C4',
                'Combination rule R1 + R2 + R3 triggered',
                50,
                $challenge['ip_address'] ?? null,
                $challenge['user_agent'] ?? null
            );
        }
    }

    private function syncChallengeRisk(string $challengeId): array
    {
        $this->evaluateCombinationRules($challengeId);

        $score = $this->riskService->getTotalScoreByChallengeId($challengeId);
        $level = $this->riskService->calculateRiskLevel($score);

        $this->loginChallengeRepository->updateRisk(
            $challengeId,
            $score,
            $level
        );

        if ($level === 'HIGH') {
            $blockedUntil = date('Y-m-d H:i:s', time() + 10 * 60);

            $this->loginChallengeRepository->blockChallenge(
                $challengeId,
                $blockedUntil
            );
        }

        return [
            'risk_score' => $score,
            'risk_level' => $level
        ];
    }
}