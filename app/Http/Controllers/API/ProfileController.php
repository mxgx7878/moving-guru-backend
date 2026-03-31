<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use App\Http\Requests\UpdateProfileRequest;
use App\Helpers\ApiResponse;

class ProfileController extends Controller
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $updatedUser = $this->profileService->updateProfile(
            $user,
            $request->validated()
        );

        return ApiResponse::success(
            'Profile updated successfully',
            $updatedUser
        );
    }
}