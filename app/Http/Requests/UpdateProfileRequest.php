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
            'hiring_role_description'    => 'nullable|string|max:5000',
            'hiring_position_type'       => 'nullable|in:permanent,temporary,substitute,weekend_cover,casual',
            'hiring_start_date'          => 'nullable|date',
            'hiring_duration'            => 'nullable|string|max:100',
            'hiring_compensation'        => 'nullable|string|max:255',
            'hiring_qualification_level' => 'nullable|in:none,intermediate,diploma,bachelors,masters,doctorate,cert_200hr,cert_500hr,cert_comprehensive,cert_specialized',
        
        ];
    }
}