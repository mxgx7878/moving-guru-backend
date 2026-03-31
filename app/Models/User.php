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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'disciplines' => 'array',
        'languages' => 'array',
        'openTo' => 'array',
        'gallery_photos' => 'array',
        'social_links' => 'array',
    ];
}