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

    public function register(Request $request)
    {

        $request->merge([
            'flexibleDates' => filter_var(
                $request->flexibleDates,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);

        $data = $request->validate([
            // Core
            'role' => 'nullable|string|in:instructor,studio',
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',

            // Common
            'bio' => 'nullable|string|max:500',
            'profileStatus' => 'nullable|string',
            'plan' => 'nullable|string',
            'disciplines' => 'nullable|array',
            'disciplines.*' => 'string',
            'openTo' => 'nullable|array',
            'openTo.*' => 'string',
            'location' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_photos' => 'nullable|array',
            'gallery_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'social_links' => 'nullable|array',

            // Instructor
            'age' => 'nullable|integer|min:18|max:100',
            'pronouns' => 'nullable|string',
            'studio' => 'nullable|string',
            'countryFrom' => 'nullable|string',
            'travelingTo' => 'nullable|string',
            'availability' => 'nullable|string',
            'availableFrom' => 'nullable|string',
            'availableTo' => 'nullable|string',
            'flexibleDates' => 'nullable|boolean',
            'languages' => 'nullable|array',
            'languages.*' => 'string',
            'lookingFor' => 'nullable|string',

            // Studio
            'studioName' => 'nullable|string',
            'contactName' => 'nullable|string',
            'country' => 'nullable|string',
            'phone' => 'nullable|string',
            'website' => 'nullable|string',
            'studioSize' => 'nullable|string',
        ]);

        $result = $this->authService->register($data, $request);

        return ApiResponse::success('User registered successfully', $result);
    }

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

    // me() — loads detail so flattenUser() works in Redux
    public function me(Request $request)
    {
        $user = $request->user()->load('detail');

        return ApiResponse::success('User fetched successfully', [
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return ApiResponse::success('Logged out successfully');
    }

    public function refresh(Request $request)
    {
        $token = $this->authService->refreshToken($request->user());

        return ApiResponse::success('Token refreshed', [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}