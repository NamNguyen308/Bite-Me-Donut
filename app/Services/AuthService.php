<?php

namespace App\Services;

use App\Core\Config;
use App\Repositories\UserRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\LoginChallengeRepository;

class AuthService
{
    private ValidationService $validationService;
    private UserRepository $userRepository;
    private LoginAttemptRepository $loginAttemptRepository;
    private LoginChallengeRepository $loginChallengeRepository;
    private RuleEngineService $ruleEngineService;
    private TokenService $tokenService;

    public function __construct()
    {
        $this->validationService = new ValidationService();
        $this->userRepository = new UserRepository();
        $this->loginAttemptRepository = new LoginAttemptRepository();
        $this->loginChallengeRepository = new LoginChallengeRepository();
        $this->ruleEngineService = new RuleEngineService();
        $this->tokenService = new TokenService();
    }

    public function login(array $data, ?string $ipAddress, ?string $userAgent): array
{
    /*
     * Support cả 2 kiểu payload:
     * - { identifier, password } từ user-login.js
     * - { phone/email, password } từ API cũ
     */
    $identifier = trim((string)($data['identifier'] ?? ''));

    if ($identifier !== '') {
        if (str_contains($identifier, '@')) {
            $data['email'] = $identifier;
            $data['phone'] = $data['phone'] ?? '';
        } else {
            $data['phone'] = preg_replace('/[\s\-\(\)]/', '', $identifier);
            $data['email'] = $data['email'] ?? '';
        }
    }

    $errors = $this->validationService->validateLogin($data);

    if (!empty($errors)) {
        return [
            'success' => false,
            'status_code' => 400,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Invalid request data',
            'errors' => $errors
        ];
    }

    $phone = trim((string)($data['phone'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    $user = $this->userRepository->findByPhoneOrEmail($phone, $email);

    if (!$user) {
        $this->loginAttemptRepository->create(
            null,
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null,
            $ipAddress,
            $userAgent,
            'FAILED',
            'USER_NOT_FOUND'
        );

        return [
            'success' => false,
            'status_code' => 401,
            'error_code' => 'INVALID_CREDENTIALS',
            'message' => 'Phone/email or password is incorrect'
        ];
    }

    if ((int)$user['is_active'] !== 1) {
        return [
            'success' => false,
            'status_code' => 403,
            'error_code' => 'ACCOUNT_INACTIVE',
            'message' => 'Account is inactive'
        ];
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        $this->loginAttemptRepository->create(
            (int)$user['id'],
            $phone !== '' ? $phone : ($user['phone'] ?? null),
            $email !== '' ? $email : ($user['email'] ?? null),
            $ipAddress,
            $userAgent,
            'FAILED',
            'INVALID_PASSWORD'
        );

        $this->ruleEngineService->evaluateFailedPassword(
            (int)$user['id'],
            $phone !== '' ? $phone : ($user['phone'] ?? null),
            $email !== '' ? $email : ($user['email'] ?? null),
            $ipAddress,
            $userAgent
        );

        return [
            'success' => false,
            'status_code' => 401,
            'error_code' => 'INVALID_CREDENTIALS',
            'message' => 'Phone/email or password is incorrect'
        ];
    }

    $this->loginAttemptRepository->create(
        (int)$user['id'],
        $phone !== '' ? $phone : ($user['phone'] ?? null),
        $email !== '' ? $email : ($user['email'] ?? null),
        $ipAddress,
        $userAgent,
        'SUCCESS',
        'LOGIN_SUCCESS'
    );

    /*
     * ADMIN FLOW:
     * Admin nhập đúng email/password thì tạo access token ngay.
     * Không tạo login_challenge.
     * Không OTP.
     * Không Twilio call.
     */
    if (($user['role'] ?? '') === 'admin') {
        $token = $this->tokenService->createAccessToken((int)$user['id']);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'ADMIN_LOGIN_SUCCESS',
            'data' => [
                'requires_otp' => false,
                'redirect_to' => 'admin_dashboard',
                'access_token' => $token['plain_token'],
                'token_type' => 'Bearer',
                'expires_in' => $token['expires_in'] ?? null,
                'expires_at' => $token['expires_at'] ?? null,
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'phone' => $user['phone'] ?? null,
                    'role' => $user['role'],
                    'is_active' => (int)$user['is_active'],
                    'created_at' => $user['created_at'] ?? null,
                    'updated_at' => $user['updated_at'] ?? null
                ]
            ]
        ];
    }

    /*
     * CUSTOMER FLOW:
     * Customer vẫn phải qua OTP.
     */
    $challengeId = $this->generateUuid();

    $expireMinutes = (int)Config::get('LOGIN_CHALLENGE_EXPIRE_MINUTES', 10);
    $expiresAt = date('Y-m-d H:i:s', time() + ($expireMinutes * 60));

    $this->loginChallengeRepository->create(
        $challengeId,
        (int)$user['id'],
        'PENDING_OTP',
        $expiresAt,
        $ipAddress,
        $userAgent
    );

    $challenge = $this->loginChallengeRepository->findById($challengeId);

    if ($challenge) {
        $this->ruleEngineService->attachRecentFailedPasswordRiskToChallenge(
            $challenge,
            $phone !== '' ? $phone : ($user['phone'] ?? null),
            $email !== '' ? $email : ($user['email'] ?? null),
            $ipAddress,
            $userAgent
        );
    }

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'PASSWORD_VERIFIED',
        'data' => [
            'requires_otp' => true,
            'login_challenge_id' => $challengeId,
            'status' => 'PENDING_OTP',
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'phone' => $user['phone'] ?? null,
                'role' => $user['role'],
                'is_active' => (int)$user['is_active']
            ]
        ]
    ];
}

    private function generateUuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function completeLogin(array $data): array
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

    if ($challenge['status'] === 'BLOCKED') {
        return [
            'success' => false,
            'status_code' => 429,
            'error_code' => 'LOGIN_CHALLENGE_BLOCKED',
            'message' => 'Login challenge is blocked'
        ];
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

    if ($challenge['status'] !== 'OTP_VERIFIED') {
        return [
            'success' => false,
            'status_code' => 403,
            'error_code' => 'OTP_REQUIRED',
            'message' => 'OTP verification is required before completing login'
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

    if ((int) $user['is_active'] !== 1) {
        return [
            'success' => false,
            'status_code' => 403,
            'error_code' => 'ACCOUNT_INACTIVE',
            'message' => 'Account is inactive'
        ];
    }

    $token = $this->tokenService->createAccessToken((int) $user['id']);

    $this->loginChallengeRepository->markAuthenticated($challengeId);

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'LOGIN_SUCCESS',
        'data' => [
            'access_token' => $token['plain_token'],
            'token_type' => 'Bearer',
            'expires_in' => $token['expires_in'],
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role']
            ]
        ]
    ];
}

public function logout(?string $plainToken): array
{
    $verifyResult = $this->tokenService->verifyAccessToken($plainToken);

    if ($verifyResult['success'] === false) {
        return $verifyResult;
    }

    $this->tokenService->revokeAccessToken($plainToken);

    return [
        'success' => true,
        'status_code' => 200,
        'message' => 'LOGOUT_SUCCESS',
        'data' => []
    ];
}

/* ═══════════════════════════════════════════════════════════
   FORGOT PASSWORD — verify identity (step 1)
═══════════════════════════════════════════════════════════ */

/**
 * Xác minh danh tính bằng email + phone.
 * Nếu khớp → tạo reset_token tạm (lưu trong session), trả về cho frontend.
 */
public function verifyIdentity(array $data): array
{
    $errors = $this->validationService->validateVerifyIdentity($data);

    if (!empty($errors)) {
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'VALIDATION_ERROR',
            'message'     => 'Invalid request data',
            'errors'      => $errors,
        ];
    }

    $email = trim($data['email']);
    $phone = trim($data['phone']);

    $user = $this->userRepository->findByEmailAndPhone($email, $phone);

    if (!$user) {
        return [
            'success'     => false,
            'status_code' => 404,
            'error_code'  => 'USER_NOT_FOUND',
            'message'     => 'No account found with that email and phone combination',
        ];
    }

    if ((int) $user['is_active'] !== 1) {
        return [
            'success'     => false,
            'status_code' => 403,
            'error_code'  => 'ACCOUNT_INACTIVE',
            'message'     => 'This account has been deactivated',
        ];
    }

    // Tạo reset token ngẫu nhiên
    $plainToken = bin2hex(random_bytes(32));  // 64-char hex string
    $tokenHash  = hash('sha256', $plainToken);
    $expiresAt  = time() + (15 * 60); // hết hạn sau 15 phút

    // Lưu vào session (không cần thay đổi schema DB)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['reset_token'] = [
        'token_hash' => $tokenHash,
        'user_id'    => (int) $user['id'],
        'expires_at' => $expiresAt,
        'used'       => false,
    ];

    return [
        'success'     => true,
        'status_code' => 200,
        'message'     => 'IDENTITY_VERIFIED',
        'data'        => [
            'reset_token' => $plainToken,
        ],
    ];
}

/* ═══════════════════════════════════════════════════════════
   FORGOT PASSWORD — reset password (step 2)
═══════════════════════════════════════════════════════════ */

/**
 * Đặt lại mật khẩu bằng reset_token + new_password.
 * Verify token từ session → update password_hash.
 */
public function resetPassword(array $data): array
{
    $errors = $this->validationService->validateResetPassword($data);

    if (!empty($errors)) {
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'VALIDATION_ERROR',
            'message'     => 'Invalid request data',
            'errors'      => $errors,
        ];
    }

    $plainToken  = trim($data['reset_token']);
    $newPassword = $data['new_password'];

    // Lấy token info từ session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $stored = $_SESSION['reset_token'] ?? null;

    if (!$stored) {
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'INVALID_RESET_TOKEN',
            'message'     => 'Reset token is invalid or session has expired',
        ];
    }

    // Token đã dùng rồi?
    if ($stored['used'] === true) {
        unset($_SESSION['reset_token']);
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'RESET_TOKEN_USED',
            'message'     => 'This reset token has already been used',
        ];
    }

    // Token hết hạn?
    if (time() > $stored['expires_at']) {
        unset($_SESSION['reset_token']);
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'INVALID_RESET_TOKEN',
            'message'     => 'Reset token has expired',
        ];
    }

    // So sánh hash token
    $providedHash = hash('sha256', $plainToken);

    if (!hash_equals($stored['token_hash'], $providedHash)) {
        return [
            'success'     => false,
            'status_code' => 400,
            'error_code'  => 'INVALID_RESET_TOKEN',
            'message'     => 'Reset token is invalid',
        ];
    }

    // Tìm user
    $userId = (int) $stored['user_id'];
    $user   = $this->userRepository->findById($userId);

    if (!$user) {
        unset($_SESSION['reset_token']);
        return [
            'success'     => false,
            'status_code' => 404,
            'error_code'  => 'USER_NOT_FOUND',
            'message'     => 'User account not found',
        ];
    }

    // Hash mật khẩu mới và cập nhật
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $this->userRepository->updatePasswordHash($userId, $passwordHash);

    // Đánh dấu token đã dùng, rồi xóa luôn
    $_SESSION['reset_token']['used'] = true;
    unset($_SESSION['reset_token']);

    return [
        'success'     => true,
        'status_code' => 200,
        'message'     => 'PASSWORD_RESET_SUCCESS',
        'data'        => [],
    ];
}
}