<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    // Register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'age' => 'nullable|integer',
            'pronouns' => 'nullable|string',
            'studio' => 'nullable|string',
            'location' => 'nullable|string',
            'countryFrom' => 'nullable|string',
            'travelingTo' => 'nullable|string',
            'availability' => 'nullable|string',
            'disciplines' => 'nullable|array',
            'languages' => 'nullable|array',
            'openTo' => 'nullable|array',
            'profileStatus' => 'nullable|string',
            'bio' => 'nullable|string|max:500',
            'plan' => 'nullable|string',
            'lookingFor' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_photos' => 'nullable|array',
            'gallery_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'social_links' => 'nullable|array',
        ]);

        $result = $this->authService->register($data);

        return ApiResponse::success('User registered successfully', $result);
    }

    // Login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login($data);

        if (!$result) {
            return ApiResponse::error('Invalid credentials', [], 422);
        }

        return ApiResponse::success('Login successful', $result);
    }

    // Authenticated user
    public function me(Request $request)
    {
        return ApiResponse::success(
            'User fetched successfully',
            $request->user()
        );
    }

    // Logout
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return ApiResponse::success('Logged out successfully');
    }

    // Refresh Token
    public function refresh(Request $request)
    {
        $token = $this->authService->refreshToken($request->user());

        return ApiResponse::success('Token refreshed', [
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}
