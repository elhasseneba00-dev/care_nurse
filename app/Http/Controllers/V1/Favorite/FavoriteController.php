<?php

namespace App\Http\Controllers\V1\Favorite;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\NurseProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * @OA\Get(
     *   path="/favorites",
     *   tags={"Favorites"},
     *   summary="List favorite nurses",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can use favorites.'], 403);
        }

        $favorites = Favorite::query()
            ->where('patient_user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $nurseIds = $favorites->pluck('nurse_user_id')->values()->all();

        if (empty($nurseIds)) {
            return response()->json(['data' => []]);
        }

        $profiles = NurseProfile::query()
            ->whereIn('user_id', $nurseIds)
            ->get()
            ->keyBy('user_id');

        $nurses = User::query()
            ->whereIn('id', $nurseIds)
            ->get()
            ->keyBy('id');

        $ratings = DB::table('reviews')
            ->whereIn('nurse_user_id', $nurseIds)
            ->selectRaw('nurse_user_id, AVG(rating) as rating_avg, COUNT(*) as reviews_count')
            ->groupBy('nurse_user_id')
            ->get()
            ->keyBy('nurse_user_id');

        // Keep same order as favorites
        $data = $favorites->map(function ($fav) use ($profiles, $nurses, $ratings) {
            $nurseId = (int) $fav->nurse_user_id;

            $nurse = $nurses->get($nurseId);
            $profile = $profiles->get($nurseId);
            $agg = $ratings->get($nurseId);

            $ratingAvg = $agg ? (float) $agg->rating_avg : 0.0;
            $reviewsCount = $agg ? (int) $agg->reviews_count : 0;

            return [
                'nurse_user_id' => $nurseId,
                'favorited_at' => $fav->created_at?->toISOString(),

                'rating_avg' => round($ratingAvg, 2),
                'reviews_count' => $reviewsCount,

                'nurse' => $nurse ? [
                    'id' => $nurse->id,
                    'full_name' => $nurse->full_name,
                    'role' => $nurse->role,
                ] : null,

                'profile' => $profile ? [
                    'city' => $profile->city,
                    'address' => $profile->address,
                    'lat' => $profile->lat,
                    'lng' => $profile->lng,
                    'coverage_km' => $profile->coverage_km,
                    'price_min' => $profile->price_min,
                    'price_max' => $profile->price_max,
                    'verified' => (bool) $profile->verified,
                ] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Post(
     *   path="/favorites/{nurseUserId}",
     *   tags={"Favorites"},
     *   summary="Add a nurse to favorites",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="nurseUserId", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=204, description="No content"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Invalid nurse")
     * )
     */
    public function store(Request $request, int $nurseUserId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can use favorites.'], 403);
        }

        $target = User::query()->findOrFail($nurseUserId);
        if ($target->role !== 'NURSE' || $target->status !== 'ACTIVE') {
            return response()->json(['message' => 'Invalid nurse.'], 422);
        }

        $profile = NurseProfile::query()->where('user_id', $target->id)->first();
        if (!$profile || !$profile->verified) {
            return response()->json(['message' => 'Nurse must be verified to be favorited.'], 422);
        }

        Favorite::query()->firstOrCreate(
            [
                'patient_user_id' => $user->id,
                'nurse_user_id' => $target->id,
            ],
            [
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Nurse added to favorites successfully.',
        ], 200);
    }

    /**
     * @OA\Delete(
     *   path="/favorites/{nurseUserId}",
     *   tags={"Favorites"},
     *   summary="Remove a nurse from favorites",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="nurseUserId", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=204, description="No content"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Request $request, int $nurseUserId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can use favorites.'], 403);
        }

        Favorite::query()
            ->where('patient_user_id', $user->id)
            ->where('nurse_user_id', $nurseUserId)
            ->delete();

        return response()->json([
            'message' => 'Nurse removed from favorites successfully.',
        ], 200);
    }
}
