<?php

namespace App\Services;

use App\Core\Config;
use App\Repositories\LoginChallengeRepository;
use App\Repositories\OtpRepository;
use App\Repositories\UserRepository;

class OtpService
{
    private LoginChallengeRepository $loginChallengeRepository;
    private OtpRepository $otpRepository;
    private UserRepository $userRepository;
    private RuleEngineService $ruleEngineService;
    private SmsService $smsService;

    public function __construct()
    {
        $this->loginChallengeRepository = new LoginChallengeRepository();
        $this->otpRepository = new OtpRepository();
        $this->userRepository = new UserRepository();
        $this->ruleEngineService = new RuleEngineService();
        $this->smsService = new SmsService();
    }

    public function requestOtp(array $data, ?string $ipAddress, ?string $userAgent): array
    {
        $challengeId = trim($data['login_challenge_id'] ?? '');

        if ($challengeId === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid request data',
                'errors' => [
                    'login_challenge_id' => 'Login challenge ID is required'
                ]
            ];
        }

        $challenge = $this->loginChallengeRepository->findById($challengeId);

        if (!$challenge) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'LOGIN_CHALLENGE_NOT_FOUND',
                'message' => 'Login challenge not found'
            ];
        }

        $blockedResponse = $this->handleBlockedChallenge($challenge);

        if ($blockedResponse !== null) {
            return $blockedResponse;
        }

        if (strtotime($challenge['expires_at']) < time()) {
            $this->loginChallengeRepository->updateStatus($challengeId, 'EXPIRED');

            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'LOGIN_CHALLENGE_EXPIRED',
                'message' => 'Login challenge has expired'
            ];
        }

        if (!in_array($challenge['status'], ['PENDING_OTP', 'OTP_SENT'], true)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid challenge status'
            ];
        }

        $this->loginChallengeRepository->incrementOtpSendCount($challengeId);

        $updatedChallenge = $this->loginChallengeRepository->findById($challengeId);

        $risk = $this->ruleEngineService->evaluateOtpRequestLimit(
            $updatedChallenge,
            $ipAddress,
            $userAgent
        );

        if ($risk['risk_level'] === 'HIGH') {
            return [
                'success' => false,
                'status_code' => 429,
                'error_code' => 'RISK_LEVEL_HIGH',
                'message' => 'OTP request is temporarily blocked due to high risk'
            ];
        }

        $maxResend = (int) Config::get('OTP_MAX_RESEND', 3);

        if ((int) $updatedChallenge['otp_send_count'] > $maxResend) {
            return [
                'success' => false,
                'status_code' => 429,
                'error_code' => 'OTP_RESEND_LIMIT_EXCEEDED',
                'message' => 'OTP request limit exceeded'
            ];
        }

        $user = $this->userRepository->findById((int) $challenge['user_id']);

        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ];
        }

        /*
         * Với mock provider:
         * - Backend tự sinh OTP.
         * - Lưu otp_hash.
         * - Trả mock_otp_code trong response.
         *
         * Với twilio_verify:
         * - Backend vẫn tạo một OTP placeholder để lưu audit trong bảng otps.
         * - Twilio Verify tự sinh và gửi mã thật.
         * - Khi verify, backend sẽ gọi Twilio VerificationCheck thay vì password_verify().
         */
        $otpCode = (string) random_int(100000, 999999);
        $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

        $expireMinutes = (int) Config::get('OTP_EXPIRE_MINUTES', 5);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expireMinutes * 60));

        $this->otpRepository->create(
            $challengeId,
            (int) $user['id'],
            $otpHash,
            'LOGIN',
            $expiresAt
        );

        $smsResult = $this->smsService->sendOtp($user['phone'], $otpCode);

        if (($smsResult['success'] ?? false) !== true) {
            return [
                'success' => false,
                'status_code' => 502,
                'error_code' => 'SMS_SEND_FAILED',
                'message' => 'OTP could not be sent',
                'errors' => [
                    'provider' => $smsResult['provider'] ?? 'unknown',
                    'provider_error_code' => $smsResult['error_code'] ?? null,
                    'provider_message' => $smsResult['message'] ?? null
                ]
            ];
        }

        $this->loginChallengeRepository->updateStatus($challengeId, 'OTP_SENT');

        $responseData = [
            'login_challenge_id' => $challengeId,
            'status' => 'OTP_SENT',
            'expires_in' => $expireMinutes * 60,
            'risk_score' => $risk['risk_score'],
            'risk_level' => $risk['risk_level'],
            'sms_provider' => $smsResult['provider']
        ];

        if (Config::get('SMS_PROVIDER', 'mock') === 'mock') {
            $responseData['mock_otp_code'] = $smsResult['otp_code'];
        }

        if (isset($smsResult['message_sid'])) {
            $responseData['sms_transaction_id'] = $smsResult['message_sid'];
        }

        if (isset($smsResult['tran_id'])) {
            $responseData['sms_transaction_id'] = $smsResult['tran_id'];
        }

        if (isset($smsResult['verification_sid'])) {
            $responseData['sms_transaction_id'] = $smsResult['verification_sid'];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OTP_SENT',
            'data' => $responseData
        ];
    }

    public function verifyOtp(array $data, ?string $ipAddress, ?string $userAgent): array
    {
        $challengeId = trim($data['login_challenge_id'] ?? '');
        $otpCode = trim($data['otp_code'] ?? '');

        $errors = [];

        if ($challengeId === '') {
            $errors['login_challenge_id'] = 'Login challenge ID is required';
        }

        if ($otpCode === '') {
            $errors['otp_code'] = 'OTP code is required';
        } elseif (!preg_match('/^[0-9]{6}$/', $otpCode)) {
            $errors['otp_code'] = 'OTP code must be 6 digits';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid request data',
                'errors' => $errors
            ];
        }

        $challenge = $this->loginChallengeRepository->findById($challengeId);

        if (!$challenge) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'LOGIN_CHALLENGE_NOT_FOUND',
                'message' => 'Login challenge not found'
            ];
        }

        $blockedResponse = $this->handleBlockedChallenge($challenge);

        if ($blockedResponse !== null) {
            return $blockedResponse;
        }

        if (strtotime($challenge['expires_at']) < time()) {
            $this->loginChallengeRepository->updateStatus($challengeId, 'EXPIRED');

            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'LOGIN_CHALLENGE_EXPIRED',
                'message' => 'Login challenge has expired'
            ];
        }

        if ($challenge['status'] !== 'OTP_SENT') {
            return [
                'success' => false,
                'status_code' => 403,
                'error_code' => 'OTP_REQUIRED',
                'message' => 'OTP must be requested before verification'
            ];
        }

        $otp = $this->otpRepository->findLatestByChallengeId($challengeId);

        if (!$otp) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'OTP_NOT_FOUND',
                'message' => 'OTP not found'
            ];
        }

        if ((int) $otp['is_used'] === 1) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'OTP_USED',
                'message' => 'OTP has already been used'
            ];
        }

        if (strtotime($otp['expires_at']) < time()) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'OTP_EXPIRED',
                'message' => 'OTP has expired'
            ];
        }

        /*
         * Nếu dùng Twilio Verify:
         * - Không dùng password_verify() với otp_hash.
         * - Mã OTP thật do Twilio sinh.
         * - Backend gọi VerificationCheck để xác minh.
         */
        if ($this->isTwilioVerifyProvider()) {
            $user = $this->userRepository->findById((int) $challenge['user_id']);

            if (!$user) {
                return [
                    'success' => false,
                    'status_code' => 404,
                    'error_code' => 'USER_NOT_FOUND',
                    'message' => 'User not found'
                ];
            }

            $verifyResult = $this->smsService->verifyTwilioOtp($user['phone'], $otpCode);

            if (($verifyResult['success'] ?? false) !== true) {
                $errorCode = $verifyResult['error_code'] ?? 'TWILIO_VERIFY_CHECK_FAILED';

                if ($this->isProviderFailure($errorCode)) {
                    return [
                        'success' => false,
                        'status_code' => 502,
                        'error_code' => 'OTP_PROVIDER_VERIFY_FAILED',
                        'message' => 'OTP verification provider failed',
                        'errors' => [
                            'provider' => $verifyResult['provider'] ?? 'twilio_verify',
                            'provider_error_code' => $errorCode,
                            'provider_message' => $verifyResult['message'] ?? null
                        ]
                    ];
                }

                return $this->handleInvalidOtp($challengeId, $ipAddress, $userAgent);
            }

            return $this->handleValidOtp($otp, $challengeId);
        }

        /*
         * Provider thường/mock:
         * - Backend tự sinh OTP.
         * - Verify bằng otp_hash trong database.
         */
        if (!password_verify($otpCode, $otp['otp_hash'])) {
            return $this->handleInvalidOtp($challengeId, $ipAddress, $userAgent);
        }

        return $this->handleValidOtp($otp, $challengeId);
    }

    private function handleInvalidOtp(string $challengeId, ?string $ipAddress, ?string $userAgent): array
    {
        $this->loginChallengeRepository->incrementOtpWrongCount($challengeId);

        $updatedChallenge = $this->loginChallengeRepository->findById($challengeId);

        $risk = $this->ruleEngineService->evaluateInvalidOtp(
            $updatedChallenge,
            $ipAddress,
            $userAgent
        );

        if ($risk['risk_level'] === 'HIGH') {
            return [
                'success' => false,
                'status_code' => 429,
                'error_code' => 'RISK_LEVEL_HIGH',
                'message' => 'OTP verification is temporarily blocked due to high risk'
            ];
        }

        $maxWrongAttempts = (int) Config::get('OTP_MAX_WRONG_ATTEMPTS', 3);

        if ((int) $updatedChallenge['otp_wrong_count'] >= $maxWrongAttempts) {
            $this->loginChallengeRepository->updateStatus($challengeId, 'EXPIRED');

            return [
                'success' => false,
                'status_code' => 429,
                'error_code' => 'OTP_TOO_MANY_ATTEMPTS',
                'message' => 'Too many invalid OTP attempts. Please login again.'
            ];
        }

        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'OTP_INVALID',
            'message' => 'OTP is invalid'
        ];
    }

    private function handleValidOtp(array $otp, string $challengeId): array
    {
        $this->otpRepository->markUsed((int) $otp['id']);

        $this->loginChallengeRepository->markOtpVerified($challengeId);

        $finalChallenge = $this->loginChallengeRepository->findById($challengeId);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OTP_VERIFIED',
            'data' => [
                'login_challenge_id' => $challengeId,
                'status' => 'OTP_VERIFIED',
                'risk_score' => (int) ($finalChallenge['risk_score'] ?? 0),
                'risk_level' => $finalChallenge['risk_level'] ?? 'LOW'
            ]
        ];
    }

    private function isTwilioVerifyProvider(): bool
    {
        return strtolower(trim(Config::get('SMS_PROVIDER', 'mock'))) === 'twilio_verify';
    }

    private function isProviderFailure(string $errorCode): bool
    {
        return in_array($errorCode, [
            'TWILIO_VERIFY_CONFIG_MISSING',
            'TWILIO_VERIFY_CURL_ERROR',
            'TWILIO_VERIFY_INVALID_RESPONSE',
            'TWILIO_VERIFY_EXCEPTION',
            'INVALID_PHONE_NUMBER'
        ], true);
    }

    private function handleBlockedChallenge(array $challenge): ?array
    {
        if ($challenge['status'] !== 'BLOCKED') {
            return null;
        }

        $blockedUntil = $challenge['blocked_until'] ?? null;

        if ($blockedUntil !== null && strtotime($blockedUntil) <= time()) {
            $this->loginChallengeRepository->updateStatus($challenge['id'], 'EXPIRED');

            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'LOGIN_CHALLENGE_EXPIRED',
                'message' => 'Login challenge has expired. Please login again.'
            ];
        }

        return [
            'success' => false,
            'status_code' => 429,
            'error_code' => 'LOGIN_CHALLENGE_BLOCKED',
            'message' => 'Login challenge is blocked. Please wait before trying again.'
        ];
    }
}