<?php

namespace App\Services;

class ProfileService
{
    public function updateProfile($user, $data)
    {
        $generateUrl = fn($path) => env('APP_URL') . '/storage/app/public/' . $path;

        $userFields   = [];
        $detailFields = [];

        // ── User table ─────────────────────────────────────────
        if (isset($data['name'])) {
            $userFields['name'] = $data['name'];
        }

        // ── Media ──────────────────────────────────────────────
        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['profile_picture']->store('profile_pictures', 'public');
            $detailFields['profile_picture'] = $generateUrl($path);
        }
        if (isset($data['background_image']) && $data['background_image'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['background_image']->store('background_images', 'public');
            $detailFields['background_image'] = $generateUrl($path);
        }
        if (isset($data['gallery_photos']) && is_array($data['gallery_photos'])) {
            $gallery = [];
            foreach ($data['gallery_photos'] as $photo) {
                if ($photo instanceof \Illuminate\Http\UploadedFile) {
                    $gallery[] = $generateUrl($photo->store('gallery', 'public'));
                }
            }
            $detailFields['gallery_photos'] = $gallery;
        }
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $detailFields['social_links'] = array_values(
                array_filter($data['social_links'], fn($l) => !is_null($l))
            );
        }

        // ── Detail fields — all camelCase, direct mapping ──────
        $detailKeys = [
            // Common
            'bio', 'plan', 'location', 'profileStatus',
            'disciplines', 'openTo',
            // Instructor
            'age', 'pronouns', 'studio',
            'countryFrom', 'travelingTo',
            'availability', 'availableFrom', 'availableTo', 'flexibleDates',
            'languages', 'lookingFor',
            // Studio
            'studioName', 'contactName', 'country', 'phone',
            'website', 'studioSize', 'instagram',

            'hiring_role_description',
            'hiring_position_type',
            'hiring_start_date',
            'hiring_duration',
            'hiring_compensation',
            'hiring_qualification_level',
        ];

        foreach ($detailKeys as $key) {
            if (array_key_exists($key, $data)) {
                $detailFields[$key] = $data[$key];
            }
        }

        // ── Persist ────────────────────────────────────────────
        if (!empty($userFields)) {
            $user->update($userFields);
        }

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