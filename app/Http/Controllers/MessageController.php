<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $userId = $request->user()->id;
        
        if ($conversation->user_id_1 !== $userId && $conversation->user_id_2 !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message' => $request->message,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'avatar' => $message->sender->avatar,
            ],
            'message' => $message->message,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at,
        ], 201);
    }

    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        $userId = $request->user()->id;
        
        if ($message->sender_id === $userId) {
            return response()->json(['error' => 'Cannot mark own message as read'], 400);
        }

        $message->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markConversationAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = $request->user()->id;
        
        if ($conversation->user_id_1 !== $userId && $conversation->user_id_2 !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
