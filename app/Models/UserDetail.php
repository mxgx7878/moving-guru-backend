<?php
// ============================================================
// app/Models/UserDetail.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $fillable = [
        'user_id',
        // Common
        'bio', 'profileStatus', 'plan', 'location',
        'disciplines', 'openTo', 'social_links',
        'profile_picture', 'background_image', 'gallery_photos',
        // Instructor
        'age', 'pronouns', 'studio',
        'countryFrom', 'travelingTo',
        'availability', 'availableFrom', 'availableTo', 'flexibleDates',
        'languages', 'lookingFor',
        // Studio
        'studioName', 'contactName', 'country', 'phone',
        'website', 'studioSize', 'instagram',
    ];

    protected $attributes = [
        'social_links'   => '[]',
        'gallery_photos' => '[]',
    ];

    protected $casts = [
        'disciplines'    => 'array',
        'languages'      => 'array',
        'openTo'         => 'array',
        'gallery_photos' => 'array',
        'social_links'   => 'array',
        'flexibleDates'  => 'boolean',
    ];

    protected $appends = [
        'profile_picture_url',
        'background_image_url',
        'gallery_photos_urls',
    ];

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
        if (!$this->gallery_photos) return [];
        return array_map(fn($p) => $this->toFullUrl($p), $this->gallery_photos);
    }

    private function toFullUrl($path)
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}