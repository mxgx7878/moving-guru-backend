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
        'status',
        'stripe_customer_id',
        'default_payment_method_id',
        'is_verified',
        'approved_at',
        'approved_by',
        'suspended_at',
        'suspension_reason',
        'rejected_at',
        'rejection_reason',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified'       => 'boolean',
        'approved_at'       => 'datetime',
        'suspended_at'      => 'datetime',
        'rejected_at'       => 'datetime',
        'last_login_at'     => 'datetime',
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

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'userId');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'userId')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'userId');
    }

    public function isActive(): bool    { return $this->status === 'active'; }
    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isSuspended(): bool { return $this->status === 'suspended'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
}
