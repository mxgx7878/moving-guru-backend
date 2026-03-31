<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PasswordService;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Helpers\ApiResponse;

class PasswordResetController extends Controller
{
    protected $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    public function forgot(ForgotPasswordRequest $request)
    {
        $this->passwordService->sendResetLink($request->validated());

        return ApiResponse::success(
            'Reset link sent to your email'
        );
    }

    public function reset(ResetPasswordRequest $request)
    {
        $this->passwordService->resetPassword(
            $request->validated()
        );

        return ApiResponse::success(
            'Password reset successful'
        );
    }
}