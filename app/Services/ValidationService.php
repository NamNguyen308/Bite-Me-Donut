<?php

namespace App\Services;

class ValidationService
{
    public function validateLogin(array $data): array
    {
        $errors = [];

        $phone = trim($data['phone'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if ($phone === '' && $email === '') {
            $errors['phone_email'] = 'Phone or email is required';
        }

        if ($phone !== '' && !$this->isValidPhone($phone)) {
            $errors['phone'] = 'Phone format is invalid';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }

    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^[0-9]{9,15}$/', $phone) === 1;
    }

    /**
     * Validate dữ liệu verify identity (forgot password step 1).
     */
    public function validateVerifyIdentity(array $data): array
    {
        $errors = [];

        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if ($email === '') {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid';
        }

        if ($phone === '') {
            $errors['phone'] = 'Phone is required';
        } elseif (!$this->isValidPhone($phone)) {
            $errors['phone'] = 'Phone format is invalid';
        }

        return $errors;
    }

    /**
     * Validate dữ liệu reset password (forgot password step 2).
     */
    public function validateResetPassword(array $data): array
    {
        $errors = [];

        $resetToken  = trim($data['reset_token'] ?? '');
        $newPassword = $data['new_password'] ?? '';

        if ($resetToken === '') {
            $errors['reset_token'] = 'Reset token is required';
        }

        if ($newPassword === '') {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $errors['new_password'] = 'Password must be at least 6 characters';
        }

        return $errors;
    }
}