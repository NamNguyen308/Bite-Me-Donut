<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function getCurrentUser(int $userId): array
    {
        $user = $this->userRepository->findPublicById($userId);

        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'user' => $this->formatUser($user)
            ]
        ];
    }

    public function getUserByIdForRequester(int $requesterId, string $requesterRole, int $targetUserId): array
    {
        if ($targetUserId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid request data',
                'errors' => [
                    'id' => 'User ID must be numeric'
                ]
            ];
        }

        if ($requesterRole !== 'admin' && $requesterId !== $targetUserId) {
            return [
                'success' => false,
                'status_code' => 403,
                'error_code' => 'IDOR_BLOCKED',
                'message' => "You are not allowed to access another user's data"
            ];
        }

        $user = $this->userRepository->findPublicById($targetUserId);

        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK',
            'data' => [
                'user' => $this->formatUser($user)
            ]
        ];
    }

    private function formatUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'is_active' => (int) $user['is_active']
        ];
    }
}
