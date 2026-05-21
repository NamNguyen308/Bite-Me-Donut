<?php

namespace App\Services;

class SpeedSMSAPI
{
    private string $accessToken;
    private string $apiBaseUrl = 'https://api.speedsms.vn/index.php';

    public function __construct(string $accessToken)
    {
        $this->accessToken = trim($accessToken);
    }

    public function getUserInfo(): array
    {
        return $this->get('/user/info');
    }

    public function sendSMS(array $phones, string $content, int $type, string $sender = ''): array
    {
        $payload = [
            'to' => $phones,
            'content' => $content,
            'sms_type' => $type,
            'sender' => $sender
        ];

        return $this->postJson('/sms/send', $payload);
    }

    public function sendVoice(string $phone, string $content): array
    {
        /*
         * Theo mẫu SpeedSMS:
         * $phone = "8491xxxxx"
         * $content chỉ là mã OTP, ví dụ "123456"
         */
        $query = http_build_query([
            'access-token' => $this->accessToken,
            'to' => $phone,
            'content' => $content
        ]);

        return $this->getWithoutBasicAuth('/voice/otp?' . $query);
    }

    private function get(string $path): array
    {
        $url = $this->apiBaseUrl . $path;

        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'status' => 'error',
                'message' => 'Could not initialize cURL'
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->accessToken . ':x',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        return $this->execute($ch);
    }

    private function getWithoutBasicAuth(string $path): array
    {
        $url = $this->apiBaseUrl . $path;

        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'status' => 'error',
                'message' => 'Could not initialize cURL'
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        return $this->execute($ch);
    }

    private function postJson(string $path, array $payload): array
    {
        $url = $this->apiBaseUrl . $path;

        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'status' => 'error',
                'message' => 'Could not initialize cURL'
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->accessToken . ':x',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30
        ]);

        return $this->execute($ch);
    }

    private function execute($ch): array
    {
        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'status' => 'error',
                'message' => $curlError !== '' ? $curlError : 'Could not call SpeedSMS API',
                'http_code' => $httpCode
            ];
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON response from SpeedSMS',
                'http_code' => $httpCode,
                'raw_response' => $rawResponse
            ];
        }

        $decoded['http_code'] = $httpCode;

        return $decoded;
    }
}