<?php

namespace App\Http\Controllers\V1\Care;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Care\ActionCareRequest;
use App\Http\Requests\V1\Care\StoreCareRequest;
use App\Http\Resources\V1\Care\CareRequestResource;
use App\Models\CareRequest;
use App\Models\CareRequestIgnore;
use App\Models\NurseProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = CareRequest::query()->orderByDesc('id');

        if ($user->role === 'PATIENT') {
            $query->where('patient_user_id', $user->id);
        } elseif ($user->role === 'NURSE') {
            $profile = NurseProfile::query()->where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'message' => 'Nurse profile is required.',
                ], 422);
            }

            // Exclude requests ignored by this nurse
            $query->whereNotIn('id', function ($sub) use ($user) {
                $sub->select('care_request_id')
                    ->from('care_request_ignores')
                    ->where('nurse_user_id', $user->id);
            });

            // Assigned requests to this nurse (any status)
            $assignedClause = function ($q) use ($user) {
                $q->where('nurse_user_id', $user->id);
            };

            // Open requests: must be PENDING and unassigned
            $openBaseClause = function ($q) {
                $q->whereNull('nurse_user_id')
                    ->where('status', 'PENDING');
            };

            $hasLatLng = $profile->lat !== null && $profile->lng !== null;
            $hasCity = !empty($profile->city);

            if (!$hasLatLng && !$hasCity) {
                return response()->json([
                    'message' => 'Nurse profile needs either (lat,lng) or city to browse open requests.',
                ], 422);
            }

            if ($hasLatLng) {
                $lat = (float) $profile->lat;
                $lng = (float) $profile->lng;
                $radiusKm = (int) ($profile->coverage_km ?? 10);

                $distanceSql = <<<SQL
(6371 * acos(
    cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))
    + sin(radians(?)) * sin(radians(lat))
))
SQL;

                // Open nearby: within radius
                $openNearbyClause = function ($q) use ($openBaseClause, $distanceSql, $lat, $lng, $radiusKm) {
                    $openBaseClause($q);
                    $q->whereNotNull('lat')
                        ->whereNotNull('lng')
                        ->whereRaw("$distanceSql <= ?", [$lat, $lng, $lat, $radiusKm]);
                };

                // Open same-city fallback (only if city exists)
                $openCityClause = function ($q) use ($openBaseClause, $profile) {
                    $openBaseClause($q);
                    $q->where('city', $profile->city);
                };

                // Combine: assigned OR (open nearby) OR (open city fallback)
                $query->where(function ($q) use ($assignedClause, $openNearbyClause, $openCityClause, $hasCity) {
                    $q->where($assignedClause)
                        ->orWhere(function ($q2) use ($openNearbyClause) {
                            $openNearbyClause($q2);
                        });

                    if ($hasCity) {
                        $q->orWhere(function ($q3) use ($openCityClause) {
                            $openCityClause($q3);
                        });
                    }
                });

                // select distance if possible
                $query->select('*')
                    ->selectRaw("$distanceSql as distance_km", [$lat, $lng, $lat])
                    // prioritize open nearby first, then open city, then assigned/history
                    ->orderByRaw("CASE
                        WHEN status = 'PENDING' AND nurse_user_id IS NULL AND distance_km IS NOT NULL THEN 0
                        WHEN status = 'PENDING' AND nurse_user_id IS NULL THEN 1
                        ELSE 2
                    END ASC")
                    ->orderBy('distance_km', 'asc');
            } else {
                // No lat/lng: city only fallback (strict)
                $city = (string) $profile->city;

                $query->where(function ($q) use ($assignedClause, $openBaseClause, $city) {
                    $q->where($assignedClause)
                        ->orWhere(function ($q2) use ($openBaseClause, $city) {
                            $openBaseClause($q2);
                            // If you have city column in care_requests later, replace with ->where('city', $city)
                            $q2->where('city', $city);
                        });
                });
            }
        } else {
            // ADMIN: all
        }

        $items = $query->paginate(20);

        return response()->json([
            'data' => CareRequestResource::collection($items)->response()->getData(true)['data'],
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Open request: allow any nurse to view while still open
        if ($user->role === 'NURSE' && $careRequest->status === 'PENDING' && $careRequest->nurse_user_id === null) {
            return response()->json(['data' => new CareRequestResource($careRequest)]);
        }

        if (!$this->isParticipant($user, $careRequest) && $user->role !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => new CareRequestResource($careRequest)]);
    }

    public function store(StoreCareRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can create care requests.'], 403);
        }

        $payload = $request->validated();

        $careRequest = CareRequest::query()->create([
            'patient_user_id' => $user->id,
            'nurse_user_id' => null,
            'care_type' => $payload['care_type'],
            'description' => $payload['description'] ?? null,
            'scheduled_at' => $payload['scheduled_at'] ?? null,
            'address' => $payload['address'],
            'city' => $payload['city'],
            'lat' => $payload['lat'],
            'lng' => $payload['lng'],
            'status' => 'PENDING',
        ]);

        return response()->json(['data' => new CareRequestResource($careRequest)], 201);
    }

    public function accept(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'Only nurses can accept.'], 403);
        }

        // Atomic claim
        $updated = CareRequest::query()
            ->where('id', $careRequest->id)
            ->where('status', 'PENDING')
            ->whereNull('nurse_user_id')
            ->update([
                'nurse_user_id' => $user->id,
                'status' => 'ACCEPTED',
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['message' => 'This request is no longer available.'], 409);
        }

        return response()->json(['data' => new CareRequestResource($careRequest->fresh())]);
    }

    public function ignore(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'Only nurses can ignore.'], 403);
        }

        // Can ignore only open pending unassigned requests (MVP)
        if (!($careRequest->status === 'PENDING' && $careRequest->nurse_user_id === null)) {
            return response()->json(['message' => 'Only open pending requests can be ignored.'], 422);
        }

        CareRequestIgnore::query()->firstOrCreate([
            'care_request_id' => $careRequest->id,
            'nurse_user_id' => $user->id,
        ], [
            'created_at' => now(),
        ]);

        return response()->json([], 204);
    }

    public function cancel(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can cancel.'], 403);
        }

        if ($careRequest->patient_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!in_array($careRequest->status, ['PENDING', 'ACCEPTED'], true)) {
            return response()->json(['message' => 'Only PENDING or ACCEPTED requests can be canceled.'], 422);
        }

        $careRequest->update(['status' => 'CANCELED']);

        return response()->json(['data' => new CareRequestResource($careRequest->fresh())]);
    }

    public function complete(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'Only nurses can complete.'], 403);
        }

        if ($careRequest->nurse_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($careRequest->status !== 'ACCEPTED') {
            return response()->json(['message' => 'Only ACCEPTED requests can be completed.'], 422);
        }

        $careRequest->update(['status' => 'DONE']);

        return response()->json(['data' => new CareRequestResource($careRequest->fresh())]);
    }

    private function isParticipant(User $user, CareRequest $careRequest): bool
    {
        return $careRequest->patient_user_id === $user->id
            || ($careRequest->nurse_user_id && $careRequest->nurse_user_id === $user->id);
    }
}
