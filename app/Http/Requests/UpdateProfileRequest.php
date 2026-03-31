<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $user = $this->user();

        return [
            'name' => 'sometimes|string|min:3',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6|confirmed',
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
            'social_links' => 'nullable|array',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'gallery_photos' => 'nullable|array',
            'gallery_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ];
    }

}