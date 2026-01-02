<?php

namespace App\Http\Controllers\V1\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *   path="/notifications",
     *   tags={"Notifications"},
     *   summary="List notifications",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="unread", in="query", required=false, @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $unread = $validated['unread'] ?? null;

        $query = $user->notifications()->orderByDesc('created_at');

        if (!is_null($unread)) {
            $query = $unread ? $user->unreadNotifications() : $user->readNotifications();
            $query->orderByDesc('created_at');
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *   path="/notifications/{id}/read",
     *   tags={"Notifications"},
     *   summary="Mark one notification as read",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=204, description="No content"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
        ], 200);
    }

    /**
     * @OA\Post(
     *   path="/notifications/read-all",
     *   tags={"Notifications"},
     *   summary="Mark all notifications as read",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=204, description="No content")
     * )
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json([
            'message' => 'All notifications marked as read.',
        ], 200);
    }
}
