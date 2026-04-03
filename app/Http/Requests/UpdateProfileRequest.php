<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize() { return true; }

    protected function prepareForValidation()
{
    if ($this->has('flexibleDates')) {
        $this->merge([
            'flexibleDates' => filter_var(
                $this->flexibleDates,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }
}

    public function rules()
    {
        return [
            // User table
            'name'             => 'sometimes|string|min:3',

            // Common
            'bio'              => 'nullable|string|max:500',
            'plan'             => 'nullable|string',
            'location'         => 'nullable|string',
            'profileStatus'    => 'nullable|string',
            'disciplines'      => 'nullable|array',
            'disciplines.*'    => 'string',
            'openTo'           => 'nullable|array',
            'openTo.*'         => 'string',
            'social_links'     => 'nullable|array',

            // Media
            'profile_picture'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'gallery_photos'   => 'nullable|array',
            'gallery_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',

            // Instructor
            'age'              => 'nullable|integer|min:18|max:100',
            'pronouns'         => 'nullable|string',
            'studio'           => 'nullable|string',
            'countryFrom'      => 'nullable|string',
            'travelingTo'      => 'nullable|string',
            'availability'     => 'nullable|string',
            'availableFrom'    => 'nullable|string',
            'availableTo'      => 'nullable|string',
            'flexibleDates'    => 'nullable|boolean',
            'languages'        => 'nullable|array',
            'languages.*'      => 'string',
            'lookingFor'       => 'nullable|string',

            // Studio
            'studioName'       => 'nullable|string',
            'contactName'      => 'nullable|string',
            'country'          => 'nullable|string',
            'phone'            => 'nullable|string',
            'website'          => 'nullable|string',
            'studioSize'       => 'nullable|string',
            'instagram'        => 'nullable|string',
        ];
    }
}