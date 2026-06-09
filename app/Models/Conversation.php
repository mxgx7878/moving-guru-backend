<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'userOneId',
        'userTwoId',
        'lastMessageAt',
    ];

    protected $casts = [
        'lastMessageAt' => 'datetime',
    ];

    /* ─── Relations ─────────────────────────────────────────────── */

    public function userOne()
    {
        return $this->belongsTo(User::class, 'userOneId')
            ->select(['id', 'name', 'email', 'role'])
            ->with('detail');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'userTwoId')
            ->select(['id', 'name', 'email', 'role'])
            ->with('detail');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversationId');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'conversationId')->latestOfMany();
    }

    /* ─── Pair normalisation ────────────────────────────────────────
     | userOneId always stores the smaller user id so the unique index
     | (userOneId, userTwoId) matches the pair regardless of who
     | started the conversation.
     */

    public function scopeBetween($query, int $a, int $b)
    {
        return $query
            ->where('userOneId', min($a, $b))
            ->where('userTwoId', max($a, $b));
    }

    public static function findOrCreateBetween(int $a, int $b): self
    {
        return static::firstOrCreate([
            'userOneId' => min($a, $b),
            'userTwoId' => max($a, $b),
        ]);
    }

    /* ─── Participant helpers ───────────────────────────────────── */

    public function hasParticipant(int $userId): bool
    {
        return (int) $this->userOneId === $userId
            || (int) $this->userTwoId === $userId;
    }

    public function otherParticipantId(int $userId): int
    {
        return (int) $this->userOneId === $userId
            ? (int) $this->userTwoId
            : (int) $this->userOneId;
    }
}