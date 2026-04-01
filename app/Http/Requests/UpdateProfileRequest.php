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
        return [
            'name' => 'sometimes|string|min:3',
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
            'social_links' => 'nullable|array',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_photos' => 'nullable|array',
            'gallery_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ];
    }
}
