<?php

namespace App\Http\Controllers\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Care\StoreCareRequest;
use App\Http\Requests\V1\Chat\StoreMessageRequest;
use App\Http\Resources\V1\Chat\MessageResource;
use App\Models\CareRequest;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ChatModerationWarning;
use App\Services\ChatModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    /**
     * @OA\Get(
     *   path="/care-requests/{careRequest}/messages",
     *   tags={"Chat"},
     *   summary="List last messages for a care request",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request, CareRequest $careRequest) : JsonResponse
    {
        /** @var User $user */
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

    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/messages",
     *   tags={"Chat"},
     *   summary="Send a message in care request chat",
     *   description="Chat is available only after ACCEPTED (or DONE). Contact sharing is masked and triggers a warning.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"message"},
     *       @OA\Property(property="message", type="string", example="Bonjour, je suis en route.")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Validation error / Chat not available")
     * )
     */
    public function store(StoreMessageRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
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

        $text = (string) $request->validated()['message'];

        [$flagged, $masked, $matches] = ChatModeration::detectAndMask($text);

        if ($flagged) {
            $user->notify(new ChatModerationWarning(
                reason: 'CONTACT_SHARING_DETECTED',
                careRequestId: $careRequest->id
            ));

            Log::warning('Chat contact sharing detected', [
                'user_id' => $user->id,
                'care_request_id' => $careRequest->id,
                'matches' => $matches,
            ]);
        }

        $msg = Message::query()->create([
            'care_request_id' => $careRequest->id,
            'sender_user_id' => $user->id,
            'message' => $masked,
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
