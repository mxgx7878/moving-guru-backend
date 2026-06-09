<?php

namespace App\Http\Controllers\API;

use App\Events\MessageSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    /* ════════════════════════════════════════════════════════════════
     |  GET /conversations — inbox, newest activity first
     * ════════════════════════════════════════════════════════════════ */
    public function index(Request $request)
    {
        $me = $request->user()->id;

        $conversations = Conversation::query()
            ->where(function ($q) use ($me) {
                $q->where('userOneId', $me)->orWhere('userTwoId', $me);
            })
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->withCount([
                'messages as unreadCount' => function ($q) use ($me) {
                    $q->where('senderId', '!=', $me)->whereNull('readAt');
                },
            ])
            ->orderByDesc('lastMessageAt')
            ->get()
            ->map(fn ($c) => $this->transformConversation($c, $me))
            ->values();

        return ApiResponse::success('Conversations loaded', [
            'conversations' => $conversations,
        ]);
    }

    /* ════════════════════════════════════════════════════════════════
     |  POST /conversations — start (or reuse) a conversation
     |  Payload: { recipientId, body }
     * ════════════════════════════════════════════════════════════════ */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipientId' => 'required|integer|exists:users,id',
            'body'        => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $me          = $request->user();
        $recipientId = (int) $request->input('recipientId');

        if ($recipientId === (int) $me->id) {
            return ApiResponse::error('You cannot message yourself.', [], 422);
        }

        $conversation = Conversation::findOrCreateBetween($me->id, $recipientId);

        $message = $this->createAndBroadcast($conversation, $me, $request->input('body'));

        // Refetch with relations + unread count so the response matches index()
        $fresh = Conversation::with(['userOne', 'userTwo', 'latestMessage'])
            ->withCount([
                'messages as unreadCount' => function ($q) use ($me) {
                    $q->where('senderId', '!=', $me->id)->whereNull('readAt');
                },
            ])
            ->find($conversation->id);

        return ApiResponse::success('Message sent', [
            'conversation' => $this->transformConversation($fresh, $me->id),
            'message'      => $this->transformMessage($message),
        ], 201);
    }

    /* ════════════════════════════════════════════════════════════════
     |  GET /conversations/{id}/messages — open a thread
     |  Side effect: incoming unread messages are marked read.
     * ════════════════════════════════════════════════════════════════ */
    public function messages(Request $request, $id)
    {
        $me           = $request->user()->id;
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return ApiResponse::error('Conversation not found', [], 404);
        }
        if (!$conversation->hasParticipant($me)) {
            return ApiResponse::error('You are not part of this conversation.', [], 403);
        }

        // Opening the thread = reading it
        Message::where('conversationId', $conversation->id)
            ->where('senderId', '!=', $me)
            ->whereNull('readAt')
            ->update(['readAt' => now()]);

        $messages = Message::where('conversationId', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => $this->transformMessage($m));

        return ApiResponse::success('Messages loaded', [
            'conversationId' => (int) $conversation->id,
            'messages'       => $messages,
        ]);
    }

    /* ════════════════════════════════════════════════════════════════
     |  POST /conversations/{id}/messages — send into existing thread
     |  Payload: { body }
     * ════════════════════════════════════════════════════════════════ */
    public function send(Request $request, $id)
    {
        $me           = $request->user();
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return ApiResponse::error('Conversation not found', [], 404);
        }
        if (!$conversation->hasParticipant($me->id)) {
            return ApiResponse::error('You are not part of this conversation.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $message = $this->createAndBroadcast($conversation, $me, $request->input('body'));

        return ApiResponse::success('Message sent', [
            'message' => $this->transformMessage($message),
        ], 201);
    }

    /* ════════════════════════════════════════════════════════════════
     |  PATCH /conversations/{id}/read — mark incoming messages read
     |  (used when a live message arrives while the thread is open)
     * ════════════════════════════════════════════════════════════════ */
    public function markRead(Request $request, $id)
    {
        $me           = $request->user()->id;
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return ApiResponse::error('Conversation not found', [], 404);
        }
        if (!$conversation->hasParticipant($me)) {
            return ApiResponse::error('You are not part of this conversation.', [], 403);
        }

        Message::where('conversationId', $conversation->id)
            ->where('senderId', '!=', $me)
            ->whereNull('readAt')
            ->update(['readAt' => now()]);

        return ApiResponse::success('Conversation marked as read', [
            'conversationId' => (int) $conversation->id,
        ]);
    }

    /* ════════════════════════════════════════════════════════════════
     |  Internals
     * ════════════════════════════════════════════════════════════════ */

    private function createAndBroadcast(Conversation $conversation, User $sender, string $body): Message
    {
        $message = Message::create([
            'conversationId' => $conversation->id,
            'senderId'       => $sender->id,
            'body'           => $body,
        ]);

        $conversation->forceFill(['lastMessageAt' => $message->created_at])->save();

        $sender->loadMissing('detail');

        // Realtime is best-effort — the message is already saved, so a Pusher
        // hiccup must never 500 the request. The other side will see it on
        // their next fetch instead.
        try {
            broadcast(new MessageSent(
                $message,
                $sender,
                $conversation->otherParticipantId($sender->id),
            ));
        } catch (\Throwable $e) {
            Log::warning('Chat: broadcast failed', [
                'messageId' => $message->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return $message;
    }

    private function transformConversation(Conversation $c, int $meId): array
    {
        $other  = (int) $c->userOneId === $meId ? $c->userTwo : $c->userOne;
        $detail = $other?->detail;

        $name = $other?->role === 'studio'
            ? ($detail?->studioName ?: $other?->name)
            : $other?->name;

        $last = $c->latestMessage;

        return [
            'id'          => (int) $c->id,
            'participant' => [
                'id'        => $other?->id,
                'name'      => $name,
                'role'      => $other?->role,
                'avatarUrl' => $detail?->profile_picture_url,
            ],
            'lastMessage' => $last ? [
                'body'      => $last->body,
                'senderId'  => (int) $last->senderId,
                'createdAt' => $last->created_at?->toISOString(),
            ] : null,
            'unreadCount'   => (int) ($c->unreadCount ?? 0),
            'lastMessageAt' => $c->lastMessageAt?->toISOString(),
        ];
    }

    private function transformMessage(Message $m): array
    {
        return [
            'id'             => $m->id,
            'conversationId' => (int) $m->conversationId,
            'senderId'       => (int) $m->senderId,
            'body'           => $m->body,
            'readAt'         => $m->readAt?->toISOString(),
            'createdAt'      => $m->created_at?->toISOString(),
        ];
    }
}