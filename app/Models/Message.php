<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversationId',
        'senderId',
        'body',
        'readAt',
    ];

    protected $casts = [
        'readAt' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversationId');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'senderId')
            ->select(['id', 'name', 'email', 'role'])
            ->with('detail');
    }
}