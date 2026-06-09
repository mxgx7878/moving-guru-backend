<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast synchronously (ShouldBroadcastNow) — Hostinger shared hosting
 * has no queue worker, so the event is pushed to Pusher inline during
 * the request.
 *
 * Channels:
 *   conversation.{id}   → live thread (open chat window)
 *   user.{recipientId}  → inbox: bump conversation list + toast anywhere
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public User $sender,
        public int $recipientId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversationId),
            new PrivateChannel('user.' . $this->recipientId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $detail = $this->sender->detail;

        $senderName = $this->sender->role === 'studio'
            ? ($detail?->studioName ?: $this->sender->name)
            : $this->sender->name;

        return [
            'message' => [
                'id'             => $this->message->id,
                'conversationId' => (int) $this->message->conversationId,
                'senderId'       => (int) $this->message->senderId,
                'body'           => $this->message->body,
                'createdAt'      => $this->message->created_at?->toISOString(),
            ],
            'sender' => [
                'id'        => $this->sender->id,
                'name'      => $senderName,
                'role'      => $this->sender->role,
                'avatarUrl' => $detail?->profile_picture_url,
            ],
            // Conversation summary from the RECIPIENT's perspective so a
            // brand-new conversation can appear in their inbox without a
            // refetch (participant = the sender).
            'conversation' => [
                'id'          => (int) $this->message->conversationId,
                'participant' => [
                    'id'        => $this->sender->id,
                    'name'      => $senderName,
                    'role'      => $this->sender->role,
                    'avatarUrl' => $detail?->profile_picture_url,
                ],
                'lastMessageAt' => $this->message->created_at?->toISOString(),
            ],
        ];
    }
}