<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register($data)
    {
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
            'disciplines'=> isset($data['disciplines']) ? $data['disciplines'] : null,
            'languages'=> isset($data['languages']) ? $data['languages'] : null,
            'openTo'=> isset($data['openTo']) ? $data['openTo'] : null,
            'profileStatus'=> $data['profileStatus'] ?? 'active',
            'bio'=> $data['bio'] ?? null,
            'plan'=> $data['plan'] ?? 'monthly',
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