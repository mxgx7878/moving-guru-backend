<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $fillable = [
        'user_id',
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
        'social_links',
        'profile_picture',
        'background_image',
        'gallery_photos',
        'lookingFor',
    ];

    protected $attributes = [
        'social_links' => '[]',
        'gallery_photos' => '[]',
    ];

    protected $casts = [
        'disciplines' => 'array',
        'languages' => 'array',
        'openTo' => 'array',
        'gallery_photos' => 'array',
        'social_links' => 'array',
    ];

    protected $appends = ['profile_picture_url', 'background_image_url', 'gallery_photos_urls'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getProfilePictureUrlAttribute()
    {
        return $this->profile_picture ? $this->toFullUrl($this->profile_picture) : null;
    }

    public function getBackgroundImageUrlAttribute()
    {
        return $this->background_image ? $this->toFullUrl($this->background_image) : null;
    }

    public function getGalleryPhotosUrlsAttribute()
    {
        if (!$this->gallery_photos) {
            return [];
        }

        return array_map(fn($photo) => $this->toFullUrl($photo), $this->gallery_photos);
    }

    private function toFullUrl($path)
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
