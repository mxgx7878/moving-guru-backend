<?php

namespace App\Models;

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
        'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $with = ['detail'];

    public function detail()
    {
        return $this->hasOne(UserDetail::class);
    }

    public function profileViews()
    {
        return $this->hasMany(ProfileView::class, 'viewed_user_id');
    }

    public function viewedProfiles()
    {
        return $this->hasMany(ProfileView::class, 'viewer_id');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
