<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->getConversations();
        
        return response()->json([
            'data' => $conversations->map(function ($conversation) use ($request) {
                $otherUser = $conversation->getOtherUser($request->user()->id);
                $latestMessage = $conversation->latestMessage();
                
                return [
                    'id' => $conversation->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email,
                        'avatar' => $otherUser->avatar,
                    ],
                    'latest_message' => $latestMessage ? [
                        'id' => $latestMessage->id,
                        'message' => $latestMessage->message,
                        'sender_id' => $latestMessage->sender_id,
                        'created_at' => $latestMessage->created_at,
                        'is_read' => $latestMessage->is_read,
                    ] : null,
                    'last_message_at' => $conversation->last_message_at,
                ];
            }),
        ]);
    }

    public function show(Conversation $conversation, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        if ($conversation->user_id_1 !== $userId && $conversation->user_id_2 !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $conversation->getOtherUser($request->user()->id)->id,
                    'name' => $conversation->getOtherUser($request->user()->id)->name,
                    'email' => $conversation->getOtherUser($request->user()->id)->email,
                    'avatar' => $conversation->getOtherUser($request->user()->id)->avatar,
                ],
            ],
            'messages' => $messages->map(function ($message) {
                return [
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
                ];
            }),
        ]);
    }

    public function getOrCreate(Request $request, int $userId): JsonResponse
    {
        $currentUserId = $request->user()->id;

        if ($currentUserId === $userId) {
            return response()->json(['error' => 'Cannot create conversation with yourself'], 400);
        }

        $conversation = Conversation::where(function ($query) use ($currentUserId, $userId) {
            $query->where('user_id_1', $currentUserId)->where('user_id_2', $userId);
        })->orWhere(function ($query) use ($currentUserId, $userId) {
            $query->where('user_id_1', $userId)->where('user_id_2', $currentUserId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_id_1' => $currentUserId,
                'user_id_2' => $userId,
            ]);
        }

        return response()->json([
            'id' => $conversation->id,
        ]);
    }
}
