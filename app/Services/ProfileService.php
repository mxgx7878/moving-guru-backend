<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function updateProfile($user, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Handle profile picture upload
        if (isset($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['profile_picture']->store('profile_pictures', 'public');
            $data['profile_picture'] = Storage::disk('public')->url($path);
        }

        // Handle background image upload
        if (isset($data['background_image']) && $data['background_image'] instanceof \Illuminate\Http\UploadedFile) {
            $path = $data['background_image']->store('background_images', 'public');
            $data['background_image'] = Storage::disk('public')->url($path);
        }

        // Handle gallery photos upload
        if (isset($data['gallery_photos']) && is_array($data['gallery_photos'])) {
            $gallery = [];
            foreach ($data['gallery_photos'] as $photo) {
                if ($photo instanceof \Illuminate\Http\UploadedFile) {
                    $path = $photo->store('gallery', 'public');
                    $gallery[] = Storage::disk('public')->url($path);
                }
            }
            $data['gallery_photos'] = $gallery;
        }

        $user->update($data);

        return $user;
    }
}
