<?php

namespace App\Services;

use App\Core\Config;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;

class TokenService
{
    private TokenRepository $tokenRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->tokenRepository = new TokenRepository();
        $this->userRepository = new UserRepository();
    }

    public function createAccessToken(int $userId): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = $this->hashToken($plainToken);

        $expireMinutes = (int) Config::get('TOKEN_EXPIRE_MINUTES', 60);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expireMinutes * 60));

        $this->tokenRepository->create(
            $userId,
            $tokenHash,
            $expiresAt
        );

        return [
            'plain_token' => $plainToken,
            'token_hash' => $tokenHash,
            'expires_in' => $expireMinutes * 60,
            'expires_at' => $expiresAt
        ];
    }

    public function verifyAccessToken(?string $plainToken): array
    {
        if ($plainToken === null || trim($plainToken) === '') {
            return [
                'success' => false,
                'status_code' => 401,
                'error_code' => 'TOKEN_MISSING',
                'message' => 'Authorization token is required'
            ];
        }

        $tokenHash = $this->hashToken($plainToken);
        $token = $this->tokenRepository->findByTokenHash($tokenHash);

        if (!$token) {
            return [
                'success' => false,
                'status_code' => 401,
                'error_code' => 'TOKEN_INVALID',
                'message' => 'Invalid token'
            ];
        }

        if ($token['revoked_at'] !== null) {
            return [
                'success' => false,
                'status_code' => 401,
                'error_code' => 'TOKEN_REVOKED',
                'message' => 'Token has been revoked'
            ];
        }

        if (strtotime($token['expires_at']) < time()) {
            return [
                'success' => false,
                'status_code' => 401,
                'error_code' => 'TOKEN_EXPIRED',
                'message' => 'Token has expired'
            ];
        }

        $user = $this->userRepository->findById((int) $token['user_id']);

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

        return [
            'success' => true,
            'status_code' => 200,
            'token' => $token,
            'user' => $user
        ];
    }

    public function revokeAccessToken(string $plainToken): void
    {
        $tokenHash = $this->hashToken($plainToken);

        $this->tokenRepository->revokeByTokenHash($tokenHash);
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}