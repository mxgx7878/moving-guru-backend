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

        // array fields ko directly assign kar sakte ho
        foreach (['disciplines','languages','openTo','gallery_photos','social_links'] as $field) {
            if(isset($data[$field])) {
                $data[$field] = $data[$field];
            }
        }
         if(isset($data['profile_picture'])){
            $path = $data['profile_picture']->store('profile_pictures','public');
            $data['profile_picture'] = Storage::url($path);
        }

        // background image
        if(isset($data['background_image'])){
            $path = $data['background_image']->store('background_images','public');
            $data['background_image'] = Storage::url($path);
        }

        // gallery photos
        if(isset($data['gallery_photos'])){
            $gallery = [];

            foreach($data['gallery_photos'] as $photo){
                $path = $photo->store('gallery','public');
                $gallery[] = Storage::url($path);
            }

            $data['gallery_photos'] = $gallery;
        }


        $user->update($data);

        return $user;
    }
}