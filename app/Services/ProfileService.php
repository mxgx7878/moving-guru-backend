<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function updateProfile($user, $data)
    {
        $userFields = [];
        $detailFields = [];

        if (isset($data['name'])) {
            $userFields['name'] = $data['name'];
        }

        // Helper function to generate correct DB URL
        $generateUrl = function ($path) {
            return env('APP_URL') . '/storage/app/public/' . $path;
        };

        // Handle profile picture
        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['profile_picture']->store('profile_pictures', 'public');
            $detailFields['profile_picture'] = $generateUrl($path);
        }

        // Handle background image
        if (isset($data['background_image']) && $data['background_image'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['background_image']->store('background_images', 'public');
            $detailFields['background_image'] = $generateUrl($path);
        }

        // Handle gallery photos
        if (isset($data['gallery_photos']) && is_array($data['gallery_photos'])) {
            $gallery = [];
            foreach ($data['gallery_photos'] as $photo) {
                if ($photo instanceof \Illuminate\Http\UploadedFile) {
                    $path = $photo->store('gallery', 'public');
                    $gallery[] = $generateUrl($path);
                }
            }
            $detailFields['gallery_photos'] = $gallery;
        }

        // Filter null values from social_links
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $detailFields['social_links'] = array_values(array_filter($data['social_links'], fn($link) => !is_null($link)));
        }

        // All remaining detail fields
        $detailKeys = [
            'age',
            'pronouns',
            'studio',
            'location',
            'countryFrom',
            'travelingTo',
            'availability',
            'disciplines',
            'languages',
            'openTo',
            'profileStatus',
            'bio',
            'plan',
            'lookingFor',
        ];

        foreach ($detailKeys as $key) {
            if (array_key_exists($key, $data)) {
                $detailFields[$key] = $data[$key];
            }
        }

        // Update user table fields
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        // Update or create detail record
        if (!empty($detailFields)) {
            $user->detail()->updateOrCreate(
                ['user_id' => $user->id],
                $detailFields
            );
        }

        $user->load('detail');

        return $user;
    }
}
