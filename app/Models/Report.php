<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'reporterId',
        'reportedUserId',
        'conversationId',
        'messageId',
        'type',
        'reason',
        'details',
        'reportedMessage',
        'contextSnapshot',
        'status',
    ];

    protected $casts = [
        'reportedMessage' => 'array',
        'contextSnapshot' => 'array',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporterId')
            ->select(['id', 'name', 'email', 'role'])
            ->with('detail');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reportedUserId')
            ->select(['id', 'name', 'email', 'role'])
            ->with('detail');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversationId');
    }

    public function message()
    {
        return $this->belongsTo(Message::class, 'messageId');
    }
}