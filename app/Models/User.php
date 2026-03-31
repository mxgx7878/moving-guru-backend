<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
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
        'background_image',
        'profile_picture',
        'gallery_photos',
        'social_links',
        'profile_views',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'social_links' => '[]',
        'gallery_photos' => '[]',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'disciplines' => 'array',
        'languages' => 'array',
        'openTo' => 'array',
        'gallery_photos' => 'array',
        'social_links' => 'array',
    ];

    protected $appends = ['profile_picture_url', 'background_image_url', 'gallery_photos_urls'];

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