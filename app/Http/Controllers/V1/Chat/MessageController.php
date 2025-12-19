<?php

namespace App\Http\Controllers\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Care\StoreCareRequest;
use App\Http\Resources\V1\Chat\MessageResource;
use App\Models\CareRequest;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, CareRequest $careRequest) : JsonResponse
    {
        $user = $request->user();

        if (!$this->canAccessChat($user, $careRequest)) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $messages = Message::query()
            ->where('care_request_id', $careRequest->id)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => MessageResource::collection($messages),
        ]);
    }

    public function store(StoreCareRequest $request, CareRequest $careRequest): JsonResponse {
        $user = $request->user();

        if (!$this->canAccessChat($user, $careRequest)) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        // MVP: chat only after accept
        if (!($careRequest->status === 'ACCEPTED' || $careRequest->status === 'DONE')) {
            return response()->json(['message' => 'Chat is available only after the request is accepted.'], 422);
        }

        $msg = Message::query()->create([
            'care_request_id' => $careRequest->id,
            'sender_user_id' => $user->id,
            'message' => $request->input('message'),
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => new MessageResource($msg),
        ], 201);
    }

    private function canAccessChat(User $user, CareRequest $careRequest) : bool
    {
        // Admin can read everything (optional)
        if ($user->role === 'ADMIN') {
            return true;
        }

        if ($careRequest->patient_user_id === $user->id) {
            return true;
        }

        if ($careRequest->nurse_user_id && $careRequest->nurse_user_id === $user->id) {
            return true;
        }

        return false;
    }
}
