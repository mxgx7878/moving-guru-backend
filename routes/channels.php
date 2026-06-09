<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| The /api/broadcasting/auth endpoint is guarded by auth:sanctum (see
| bootstrap/app.php), so $user here is the Bearer-token user making the
| Echo subscription request.
|
| Channel families:
|   user.{id}                → personal channel: in-app notifications +
|                              inbox events. Only the owner may listen.
|   conversation.{id}        → live chat thread. Only the two participants.
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('id', $conversationId)
        ->where(function ($q) use ($user) {
            $q->where('userOneId', $user->id)
              ->orWhere('userTwoId', $user->id);
        })
        ->exists();
});