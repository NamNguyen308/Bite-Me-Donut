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
}