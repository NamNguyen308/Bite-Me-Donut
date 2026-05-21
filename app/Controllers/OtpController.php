<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\OtpService;

class OtpController
{
    private OtpService $otpService;

    public function __construct()
    {
        $this->otpService = new OtpService();
    }

    public function requestOtp(Request $request): void
    {
        $result = $this->otpService->requestOtp(
            $request->body(),
            $request->ip(),
            $request->userAgent()
        );

        if ($result['success'] === true) {
            Response::success(
                $result['message'],
                $result['data'],
                $result['status_code']
            );
        }

        Response::error(
            $result['error_code'],
            $result['message'],
            $result['status_code'],
            $result['errors'] ?? []
        );
    }

    public function verifyOtp(Request $request): void
{
    $result = $this->otpService->verifyOtp(
        $request->body(),
        $request->ip(),
        $request->userAgent()
    );

    if ($result['success'] === true) {
        Response::success(
            $result['message'],
            $result['data'],
            $result['status_code']
        );
    }

    Response::error(
        $result['error_code'],
        $result['message'],
        $result['status_code'],
        $result['errors'] ?? []
    );
}
}