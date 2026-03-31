<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthService
{
    public function register($data)
    {
        // Handle profile picture upload
        $profilePicture = null;
        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['profile_picture']->store('profile_pictures', 'public');
            $profilePicture = Storage::disk('public')->url($path);
        }

        // Handle background image upload
        $backgroundImage = null;
        if (isset($data['background_image']) && $data['background_image'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['background_image']->store('background_images', 'public');
            $backgroundImage = Storage::disk('public')->url($path);
        }

        // Handle gallery photos upload
        $galleryPhotos = null;
        if (isset($data['gallery_photos']) && is_array($data['gallery_photos'])) {
            $galleryPhotos = [];
            foreach ($data['gallery_photos'] as $photo) {
                if ($photo instanceof \Illuminate\Http\UploadedFile) {
                    $path = $photo->store('gallery', 'public');
                    $galleryPhotos[] = Storage::disk('public')->url($path);
                }
            }
        }

        $user = User::create([
            'name'=> $data['name'],
            'email'=> $data['email'],
            'password'=> Hash::make($data['password']),
            'age'=> $data['age'] ?? null,
            'pronouns'=> $data['pronouns'] ?? null,
            'studio'=> $data['studio'] ?? null,
            'location'=> $data['location'] ?? null,
            'countryFrom'=> $data['countryFrom'] ?? null,
            'travelingTo'=> $data['travelingTo'] ?? null,
            'availability'=> $data['availability'] ?? null,
            'disciplines'=> $data['disciplines'] ?? null,
            'languages'=> $data['languages'] ?? null,
            'openTo'=> $data['openTo'] ?? null,
            'profileStatus'=> $data['profileStatus'] ?? 'active',
            'bio'=> $data['bio'] ?? null,
            'plan'=> $data['plan'] ?? 'monthly',
            'profile_picture'=> $profilePicture,
            'background_image'=> $backgroundImage,
            'gallery_photos'=> $galleryPhotos,
            'social_links'=> $data['social_links'] ?? [],
            'profile_views'=> $data['profile_views'] ?? 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    public function login($data)
    {
        if (!Auth::attempt([
            'email'=>$data['email'],
            'password'=>$data['password']
        ])) {
            return false;
        }

        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'=>$user,
            'access_token'=>$token,
            'token_type'=>'Bearer'
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
    }

    public function refreshToken($user)
    {
        $user->currentAccessToken()->delete();

        return $user->createToken('auth_token')->plainTextToken;
    }
}