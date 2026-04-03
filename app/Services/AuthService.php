<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register($data, $request = null)
    {
        $generateUrl = fn($path) => env('APP_URL') . '/storage/app/public/' . $path;

        // ── File uploads ───────────────────────────────────────
        $profilePicture  = null;
        $backgroundImage = null;
        $galleryPhotos   = [];

        if ($request?->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $profilePicture = $generateUrl($path);
        }
        if ($request?->hasFile('background_image')) {
            $path = $request->file('background_image')->store('background_images', 'public');
            $backgroundImage = $generateUrl($path);
        }
        if ($request?->hasFile('gallery_photos')) {
            foreach ($request->file('gallery_photos') as $photo) {
                $galleryPhotos[] = $generateUrl($photo->store('gallery', 'public'));
            }
        }

        // ── Create user ────────────────────────────────────────
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'] ?? 'instructor',
        ]);

        // ── Build detail — all keys already camelCase ──────────
        $role       = $data['role'] ?? 'instructor';
        $detailData = [
            'bio'             => $data['bio']          ?? null,
            'profileStatus'   => $data['profileStatus'] ?? 'active',
            'plan'            => $data['plan']          ?? 'monthly',
            'disciplines'     => $data['disciplines']   ?? null,
            'openTo'          => $data['openTo']        ?? null,
            'location'        => $data['location']      ?? null,
            'social_links'    => $data['social_links']  ?? [],
            'profile_picture' => $profilePicture,
            'background_image'=> $backgroundImage,
            'gallery_photos'  => $galleryPhotos,
        ];

        if ($role === 'instructor') {
            $detailData += [
                'age'           => $data['age']           ?? null,
                'pronouns'      => $data['pronouns']      ?? null,
                'studio'        => $data['studio']        ?? null,
                'countryFrom'   => $data['countryFrom']   ?? null,
                'travelingTo'   => $data['travelingTo']   ?? null,
                'availability'  => $data['availability']  ?? null,
                'availableFrom' => $data['availableFrom'] ?? null,
                'availableTo'   => $data['availableTo']   ?? null,
                'flexibleDates' => $data['flexibleDates'] ?? false,
                'languages'     => $data['languages']     ?? null,
                'lookingFor'    => $data['lookingFor']    ?? null,
            ];
        }

        if ($role === 'studio') {
            $detailData += [
                'studioName'  => $data['studioName']  ?? null,
                'contactName' => $data['contactName'] ?? null,
                'country'     => $data['country']     ?? null,
                'phone'       => $data['phone']       ?? null,
                'website'     => $data['website']     ?? null,
                'studioSize'  => $data['studioSize']  ?? null,
            ];
        }

        $user->detail()->create($detailData);
        $user->load('detail');

        return [
            'user'         => $user,
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type'   => 'Bearer',
        ];
    }

    public function login($data)
    {
        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return false;
        }

        $user = Auth::user()->load('detail');

        return [
            'user'         => $user,
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type'   => 'Bearer',
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