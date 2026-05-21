<?php

namespace App\Services;

use App\Core\Config;
use Throwable;

class SmsService
{
    public function sendOtp(string $phone, string $otpCode): array
    {
        $provider = strtolower(trim(Config::get('SMS_PROVIDER', 'mock')));

        if ($provider === 'twilio_verify') {
            return $this->sendViaTwilioVerify($phone, $otpCode);
        }

        return $this->sendViaMock($phone, $otpCode);
    }

    private function sendViaMock(string $phone, string $otpCode): array
    {
        return [
            'success' => true,
            'provider' => 'mock',
            'phone' => $phone,
            'otp_code' => $otpCode,
            'message' => 'Mock OTP generated successfully'
        ];
    }

    private function sendViaTwilioVerify(string $phone, string $otpCode): array
    {
        $accountSid = trim(Config::get('TWILIO_ACCOUNT_SID', ''));
        $authToken = trim(Config::get('TWILIO_AUTH_TOKEN', ''));
        $serviceSid = trim(Config::get('TWILIO_VERIFY_SERVICE_SID', ''));
        $channel = strtolower(trim(Config::get('TWILIO_VERIFY_CHANNEL', 'call')));
        $apiBaseUrl = rtrim(Config::get('TWILIO_VERIFY_API_URL', 'https://verify.twilio.com/v2'), '/');

        if ($accountSid === '' || $authToken === '' || $serviceSid === '') {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_CONFIG_MISSING',
                'message' => 'Twilio Verify configuration is missing'
            ];
        }

        if (!in_array($channel, ['sms', 'call'], true)) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_CHANNEL_INVALID',
                'message' => 'Twilio Verify channel must be sms or call'
            ];
        }

        if (!preg_match('/^[0-9]{4,10}$/', $otpCode)) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_CUSTOM_CODE_INVALID',
                'message' => 'OTP code must be 4 to 10 digits for Twilio Verify CustomCode'
            ];
        }

        $toPhone = $this->normalizePhoneToE164($phone);

        if ($toPhone === null) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'INVALID_PHONE_NUMBER',
                'message' => 'Phone number must be a valid Vietnam phone number'
            ];
        }

        $url = $apiBaseUrl . '/Services/' . rawurlencode($serviceSid) . '/Verifications';

        /*
         * Twilio Verify sẽ gửi đúng mã OTP do backend tự sinh.
         * Nhờ CustomCode, backend vẫn có thể hash OTP vào DB và tự verify như logic hiện tại.
         */
        $payload = [
    'To' => $toPhone,
    'Channel' => $channel
];

        try {
            $ch = curl_init($url);

            if ($ch === false) {
                return [
                    'success' => false,
                    'provider' => 'twilio_verify',
                    'error_code' => 'CURL_INIT_FAILED',
                    'message' => 'Could not initialize cURL'
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $accountSid . ':' . $authToken,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_TIMEOUT => 30
            ]);

            $rawResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($rawResponse === false) {
                return [
                    'success' => false,
                    'provider' => 'twilio_verify',
                    'error_code' => 'TWILIO_VERIFY_CURL_ERROR',
                    'message' => $curlError !== '' ? $curlError : 'Could not call Twilio Verify API'
                ];
            }

            $decoded = json_decode($rawResponse, true);

            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'provider' => 'twilio_verify',
                    'error_code' => 'TWILIO_VERIFY_INVALID_RESPONSE',
                    'message' => 'Invalid JSON response from Twilio Verify',
                    'http_code' => $httpCode,
                    'raw_response' => $rawResponse
                ];
            }

            if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['sid'])) {
                return [
                    'success' => true,
                    'provider' => 'twilio_verify',
                    'phone' => $toPhone,
                    'verification_sid' => $decoded['sid'],
                    'verification_status' => $decoded['status'] ?? null,
                    'channel' => $decoded['channel'] ?? $channel,
                    'service_sid' => $decoded['service_sid'] ?? $serviceSid
                ];
            }

            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => $decoded['code'] ?? 'TWILIO_VERIFY_SEND_FAILED',
                'message' => $decoded['message'] ?? 'Twilio Verify could not send OTP',
                'http_code' => $httpCode,
                'raw_response' => $decoded
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_EXCEPTION',
                'message' => $e->getMessage()
            ];
        }
    }

    private function normalizePhoneToE164(string $phone): ?string
    {
        $phone = trim($phone);
        $phone = str_replace([' ', '.', '-', '(', ')'], '', $phone);

        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+84')) {
            $normalized = '+84' . substr($phone, 3);
        } elseif (str_starts_with($phone, '84')) {
            $normalized = '+' . $phone;
        } elseif (str_starts_with($phone, '0')) {
            $normalized = '+84' . substr($phone, 1);
        } else {
            return null;
        }

        if (!preg_match('/^\+84[0-9]{9}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public function verifyTwilioOtp(string $phone, string $otpCode): array
{
    $accountSid = trim(Config::get('TWILIO_ACCOUNT_SID', ''));
    $authToken = trim(Config::get('TWILIO_AUTH_TOKEN', ''));
    $serviceSid = trim(Config::get('TWILIO_VERIFY_SERVICE_SID', ''));
    $apiBaseUrl = rtrim(Config::get('TWILIO_VERIFY_API_URL', 'https://verify.twilio.com/v2'), '/');

    if ($accountSid === '' || $authToken === '' || $serviceSid === '') {
        return [
            'success' => false,
            'provider' => 'twilio_verify',
            'error_code' => 'TWILIO_VERIFY_CONFIG_MISSING',
            'message' => 'Twilio Verify configuration is missing'
        ];
    }

    $toPhone = $this->normalizePhoneToE164($phone);

    if ($toPhone === null) {
        return [
            'success' => false,
            'provider' => 'twilio_verify',
            'error_code' => 'INVALID_PHONE_NUMBER',
            'message' => 'Phone number must be a valid Vietnam phone number'
        ];
    }

    $url = $apiBaseUrl . '/Services/' . rawurlencode($serviceSid) . '/VerificationCheck';

    $payload = [
        'To' => $toPhone,
        'Code' => $otpCode
    ];

    try {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $accountSid . ':' . $authToken,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_CURL_ERROR',
                'message' => $curlError !== '' ? $curlError : 'Could not call Twilio Verify API'
            ];
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error_code' => 'TWILIO_VERIFY_INVALID_RESPONSE',
                'message' => 'Invalid JSON response from Twilio Verify',
                'http_code' => $httpCode,
                'raw_response' => $rawResponse
            ];
        }

        if (
            $httpCode >= 200 &&
            $httpCode < 300 &&
            ($decoded['valid'] ?? false) === true &&
            ($decoded['status'] ?? '') === 'approved'
        ) {
            return [
                'success' => true,
                'provider' => 'twilio_verify',
                'verification_sid' => $decoded['sid'] ?? null,
                'status' => $decoded['status'] ?? null,
                'valid' => true
            ];
        }

        return [
            'success' => false,
            'provider' => 'twilio_verify',
            'error_code' => $decoded['code'] ?? 'TWILIO_VERIFY_CHECK_FAILED',
            'message' => $decoded['message'] ?? 'OTP verification failed',
            'http_code' => $httpCode,
            'raw_response' => $decoded
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'provider' => 'twilio_verify',
            'error_code' => 'TWILIO_VERIFY_EXCEPTION',
            'message' => $e->getMessage()
        ];
    }
}
}