<?php

namespace App\Http\Controllers\V1\Care;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Care\ActionCareRequest;
use App\Http\Requests\V1\Care\RebookCareRequest;
use App\Http\Requests\V1\Care\StoreCareRequest;
use App\Http\Resources\V1\Care\CareRequestResource;
use App\Jobs\NotifyNearByNursesOfNewCareRequest;
use App\Models\CareRequest;
use App\Models\CareRequestIgnore;
use App\Models\NurseProfile;
use App\Models\User;
use App\Notifications\CareRequestNotification;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareRequestController extends Controller
{
    /**
     * @OA\Get(
     *   path="/care-requests",
     *   tags={"Care Requests"},
     *   summary="List care requests for current user",
     *   description="PATIENT: returns own requests. NURSE: returns assigned + nearby open BROADCAST + open TARGETED (to self). ADMIN: all.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Important: ne pas appliquer un orderBy global ici, sinon il passe avant le CASE
        $query = CareRequest::query();
//--------------------------------------------------------------------------------
        if ($user->role === 'PATIENT') {
            $query->where('patient_user_id', $user->id)
                ->orderByDesc('id');

           /* if ($user->role === 'PATIENT') {
                $query->where('patient_user_id', $user->id);

                // Optionnel: calculer distance_km si un point de référence est fourni
                $refLat = $request->query('lat');
                $refLng = $request->query('lng');

                if ($refLat !== null && $refLng !== null) {
                    $refLat = (float) $refLat;
                    $refLng = (float) $refLng;

                    $distanceSql = <<<SQL
(6371 * acos(
    cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))
    + sin(radians(?)) * sin(radians(lat))
))
SQL;

                    $query->select('*')
                        ->selectRaw("$distanceSql as distance_km", [$refLat, $refLng, $refLat])
                        // PostgreSQL: ne pas trier par alias dans certains contextes, répéter l'expression
                        ->orderByRaw("COALESCE(($distanceSql), 999999) ASC", [$refLat, $refLng, $refLat]);
                }

                $query->orderByDesc('id');  */
//--------------------------------------------------------------------------------
        } elseif ($user->role === 'NURSE') {
            $profile = NurseProfile::query()->where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json(['message' => 'Nurse profile is required.'], 422);
            }

            $query->whereNotIn('id', function ($sub) use ($user) {
                $sub->select('care_request_id')
                    ->from('care_request_ignores')
                    ->where('nurse_user_id', $user->id);
            });

            $assignedClause = function ($q) use ($user) {
                $q->where('nurse_user_id', $user->id);
            };

            $openBaseClause = function ($q) {
                $q->whereNull('nurse_user_id')
                    ->where('status', 'PENDING');
            };

            $openBroadcastClause = function ($q) use ($openBaseClause) {
                $openBaseClause($q);
                $q->where('visibility', 'BROADCAST');
            };

            $openTargetedToMeClause = function ($q) use ($openBaseClause, $user) {
                $openBaseClause($q);
                $q->where('visibility', 'TARGETED')
                    ->where('target_nurse_user_id', $user->id);
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

                $openNearbyBroadcastClause = function ($q) use ($openBroadcastClause, $distanceSql, $lat, $lng, $radiusKm) {
                    $openBroadcastClause($q);
                    $q->whereNotNull('lat')
                        ->whereNotNull('lng')
                        ->whereRaw("$distanceSql <= ?", [$lat, $lng, $lat, $radiusKm]);
                };

                $openCityBroadcastClause = function ($q) use ($openBroadcastClause, $profile) {
                    $openBroadcastClause($q);
                    $q->where('city', $profile->city);
                };

                $query->where(function ($q) use ($assignedClause, $openTargetedToMeClause, $openNearbyBroadcastClause, $openCityBroadcastClause, $hasCity) {
                    $q->where($assignedClause)
                        ->orWhere(fn ($qT) => $openTargetedToMeClause($qT))
                        ->orWhere(fn ($q2) => $openNearbyBroadcastClause($q2));

                    if ($hasCity) {
                        $q->orWhere(fn ($q3) => $openCityBroadcastClause($q3));
                    }
                });


                $query->select('*')
                    ->selectRaw("$distanceSql as distance_km", [$lat, $lng, $lat]);

                $query->orderByRaw(
                    "CASE
        WHEN status = 'PENDING' AND nurse_user_id IS NULL AND visibility = 'TARGETED' AND target_nurse_user_id = ? THEN 0
        WHEN status = 'PENDING' AND nurse_user_id IS NULL AND visibility = 'BROADCAST' THEN 1
        ELSE 2
    END ASC",
                    [$user->id]
                );

// IMPORTANT: ne pas utiliser l'alias distance_km dans ORDER BY sous PostgreSQL
                $query->orderByRaw(
                    "COALESCE(($distanceSql), 999999) ASC",
                    [$lat, $lng, $lat]
                );

                $query->orderByDesc('id');
            } else {
                $city = (string) $profile->city;

                $query->where(function ($q) use ($assignedClause, $openTargetedToMeClause, $openBroadcastClause, $city) {
                    $q->where($assignedClause)
                        ->orWhere(fn ($qT) => $openTargetedToMeClause($qT))
                        ->orWhere(function ($q2) use ($openBroadcastClause, $city) {
                            $openBroadcastClause($q2);
                            $q2->where('city', $city);
                        });
                });

                $query->orderByRaw(
                    "CASE
                        WHEN status = 'PENDING' AND nurse_user_id IS NULL AND visibility = 'TARGETED' AND target_nurse_user_id = ? THEN 0
                        WHEN status = 'PENDING' AND nurse_user_id IS NULL AND visibility = 'BROADCAST' THEN 1
                        ELSE 2
                    END ASC",
                    [$user->id]
                )->orderByDesc('id');
            }
        } else {
            $query->orderByDesc('id');
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

    /**
     * @OA\Get(
     *   path="/care-requests/{careRequest}",
     *   tags={"Care Requests"},
     *   summary="Get a care request",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="careRequest",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // TARGETED:
        // Open request: allow nurse to view only if BROADCAST open OR (TARGETED open and targeted to them)
        if ($user->role === 'NURSE' && $careRequest->status === 'PENDING' && $careRequest->nurse_user_id === null) {
            if ($careRequest->visibility === 'BROADCAST') {
                return response()->json(['data' => new CareRequestResource($careRequest)]);
            }

            if ($careRequest->visibility === 'TARGETED' && (int) $careRequest->target_nurse_user_id === (int) $user->id) {
                return response()->json(['data' => new CareRequestResource($careRequest)]);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->isParticipant($user, $careRequest) && $user->role !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => new CareRequestResource($careRequest)]);
    }

    /**
     * @OA\Post(
     *   path="/care-requests",
     *   tags={"Care Requests"},
     *   summary="Create a new care request",
     *   description="PATIENT only. Supports BROADCAST (default) or TARGETED (to a specific verified nurse).",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"care_type","address","city","lat","lng"},
     *       @OA\Property(property="care_type", type="string", example="Injection"),
     *       @OA\Property(property="description", type="string", nullable=true, example="Besoin d'une injection IM"),
     *       @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true, example="2026-01-02T10:00:00Z"),
     *       @OA\Property(property="address", type="string", example="Tevragh Zeina"),
     *       @OA\Property(property="city", type="string", example="Nouakchott"),
     *       @OA\Property(property="lat", type="number", format="float", example=18.102),
     *       @OA\Property(property="lng", type="number", format="float", example=-15.958),
     *       @OA\Property(property="visibility", type="string", enum={"BROADCAST","TARGETED"}, example="BROADCAST"),
     *       @OA\Property(property="target_nurse_user_id", type="integer", nullable=true, example=123),
     *       @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-01-02T10:10:00Z")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreCareRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can create care requests.'], 403);
        }

        $payload = $request->validated();

        $visibility = $payload['visibility'] ?? 'BROADCAST';
        $targetNurseUserId = $payload['target_nurse_user_id'] ?? null;

        if ($visibility === 'TARGETED') {
            /** @var User|null $targetUser */
            $targetUser = User::query()->find($targetNurseUserId);

            if (!$targetUser || $targetUser->role !== 'NURSE' || $targetUser->status !== 'ACTIVE') {
                return response()->json(['message' => 'Target nurse is invalid.'], 422);
            }

            $targetProfile = NurseProfile::query()->where('user_id', $targetUser->id)->first();
            if (!$targetProfile || !$targetProfile->verified) {
                return response()->json(['message' => 'Target nurse must be verified.'], 422);
            }
        }

        $careRequest = CareRequest::query()->create([
            'patient_user_id' => $user->id,
            'nurse_user_id' => null,
            'visibility' => $visibility,
            'target_nurse_user_id' => $visibility === 'TARGETED' ? (int) $targetNurseUserId : null,
            'expires_at' => $payload['expires_at'] ?? null,
            'care_type' => $payload['care_type'],
            'description' => $payload['description'] ?? null,
            'scheduled_at' => $payload['scheduled_at'] ?? null,
            'address' => $payload['address'],
            'city' => $payload['city'],
            'lat' => $payload['lat'],
            'lng' => $payload['lng'],
            'status' => 'PENDING',
        ]);

        Audit::log($user, 'CARE_REQUEST_CREATED', 'CareRequest', $careRequest->id, [
            'visibility' => $careRequest->visibility ?? null,
            'city' => $careRequest->city ?? null,
        ], $request);

        // Notify the patient (self)
        $request->user()->notify(new CareRequestNotification(
            event: 'CREATED',
            careRequest: $careRequest,
            message: $visibility === 'TARGETED'
                ? "Votre demande a été envoyée à l'infirmier sélectionné."
                : "Votre demande a été créée et envoyée aux infirmiers proches."
        ));

        if ($visibility === 'TARGETED') {
            // Notify only the targeted nurse (no broadcast job)
            User::query()->find((int) $targetNurseUserId)
                ?->notify(new CareRequestNotification(
                    event: 'CREATED',
                    careRequest: $careRequest,
                    message: "Nouvelle demande ciblée (vous êtes sélectionné)."
                ));
        } else {
            // Broadcast
            NotifyNearByNursesOfNewCareRequest::dispatch($careRequest->id);
        }

        return response()->json(['data' => new CareRequestResource($careRequest)], 201);
    }

    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/accept",
     *   tags={"Care Requests"},
     *   summary="Accept an open care request",
     *   description="NURSE only. For TARGETED, only the targeted nurse can accept.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=409, description="No longer available")
     * )
     */
    public function accept(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'Only nurses can accept.'], 403);
        }

        // TARGETED: Only targeted nurse can accept targeted requests
        $base = CareRequest::query()
            ->where('id', $careRequest->id)
            ->where('status', 'PENDING')
            ->whereNull('nurse_user_id');

        if ($careRequest->visibility === 'TARGETED') {
            $base->where('visibility', 'TARGETED')
                ->where('target_nurse_user_id', $user->id);
        } else {
            $base->where('visibility', 'BROADCAST');
        }

        $updated = $base->update([
            'nurse_user_id' => $user->id,
            'status' => 'ACCEPTED',
            'updated_at' => now(),
        ]);

        if ($updated === 0) {
            return response()->json(['message' => 'This request is no longer available.'], 409);
        }

        $fresh = CareRequest::query()->findOrFail($careRequest->id);

        Audit::log($user, 'CARE_REQUEST_ACCEPTED', 'CareRequest', $fresh->id, [], $request);

        // notifier patient + nurse
        User::query()->find($fresh->patient_user_id)?->notify(new CareRequestNotification(
            event: 'ACCEPTED',
            careRequest: $fresh,
            message: "Votre demande a été acceptée par un infirmier."
        ));

        $request->user()->notify(new CareRequestNotification(
            event: 'ACCEPTED',
            careRequest: $fresh,
            message: "Vous avez accepté une demande."
        ));

        return response()->json([
            'data' => new CareRequestResource($fresh),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/ignore",
     *   tags={"Care Requests"},
     *   summary="Ignore an open care request",
     *   description="NURSE only. Only for open BROADCAST requests.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=204, description="No content"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Invalid request state")
     * )
     */
    public function ignore(ActionCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'Only nurses can ignore.'], 403);
        }

        // Can ignore only open pending unassigned BROADCAST requests (targeted doesn't make sense to ignore)
        if (!($careRequest->status === 'PENDING' && $careRequest->nurse_user_id === null && $careRequest->visibility === 'BROADCAST')) {
            return response()->json(['message' => 'Only open pending BROADCAST requests can be ignored.'], 422);
        }

        CareRequestIgnore::query()->firstOrCreate([
            'care_request_id' => $careRequest->id,
            'nurse_user_id' => $user->id,
        ], [
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Request ignored successfully.',
        ], 200);
    }

    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/cancel",
     *   tags={"Care Requests"},
     *   summary="Cancel a care request",
     *   description="PATIENT only. Allowed statuses: PENDING or ACCEPTED.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Invalid status")
     * )
     */
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

        Audit::log($user, 'CARE_REQUEST_CANCELED', 'CareRequest', $careRequest->id, [], $request);

        // notifier patient + nurse si assignée
        $request->user()->notify(new CareRequestNotification(
            event: 'CANCELED',
            careRequest: $careRequest,
            message: "Vous avez annulé la demande."
        ));

        if ($careRequest->nurse_user_id) {
            User::query()->find($careRequest->nurse_user_id)
                ?->notify(new CareRequestNotification(
                    event: 'CANCELED',
                    careRequest: $careRequest,
                    message: "La demande a été annulée par le patient."
                ));
        } elseif ($careRequest->visibility === 'TARGETED' && $careRequest->target_nurse_user_id) {
            // If it was targeted but never accepted, notify the targeted nurse too
            User::query()->find($careRequest->target_nurse_user_id)
                ?->notify(new CareRequestNotification(
                    event: 'CANCELED',
                    careRequest: $careRequest,
                    message: "La demande ciblée a été annulée par le patient."
                ));
        }

        return response()->json(['data' => new CareRequestResource($careRequest->fresh())]);
    }

    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/complete",
     *   tags={"Care Requests"},
     *   summary="Complete a care request",
     *   description="NURSE only. Allowed status: ACCEPTED. Marks it as DONE.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Invalid status")
     * )
     */
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

        Audit::log($user, 'CARE_REQUEST_COMPLETED', 'CareRequest', $careRequest->id, [], $request);

        // notifier patient + nurse
        User::query()->find($careRequest->patient_user_id)
            ?->notify(new CareRequestNotification(
                event: 'DONE',
                careRequest: $careRequest,
                message: "La prestation est terminée. Vous pouvez laisser un avis."
            ));

        $request->user()->notify(new CareRequestNotification(
            event: 'DONE',
            careRequest: $careRequest,
            message: "Vous avez marqué la demande comme terminée."
        ));

        return response()->json(['data' => new CareRequestResource($careRequest->fresh())]);
    }

    private function isParticipant(User $user, CareRequest $careRequest): bool
    {
        return $careRequest->patient_user_id === $user->id
            || ($careRequest->nurse_user_id && $careRequest->nurse_user_id === $user->id);
    }


    /**
     * @OA\Post(
     *   path="/care-requests/{careRequest}/rebook",
     *   tags={"Care Requests"},
     *   summary="Rebook based on a previous care request",
     *   description="PATIENT only. Only allowed when original request is DONE. Can rebook as TARGETED (same nurse) or BROADCAST.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="careRequest", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="same_nurse", type="boolean", example=true),
     *       @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true, example="2026-01-02T10:00:00Z"),
     *       @OA\Property(property="expires_at", type="string", format="date-time", nullable=true, example="2026-01-02T10:10:00Z")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Invalid status")
     * )
     */
    public function rebook(RebookCareRequest $request, CareRequest $careRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Only patients can rebook.'], 403);
        }

        if ($careRequest->patient_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Recommended: allow to rebook only after DONE (you can relax later)
        if ($careRequest->status !== 'DONE') {
            return response()->json(['message' => 'Only DONE requests can be rebooked.'], 422);
        }

        $payload = $request->validated();
        $sameNurse = array_key_exists('same_nurse', $payload) ? (bool) $payload['same_nurse'] : true;

        $visibility = 'BROADCAST';
        $targetNurseUserId = null;

        if ($sameNurse && $careRequest->nurse_user_id) {
            $candidate = User::query()->find($careRequest->nurse_user_id);

            if ($candidate && $candidate->role === 'NURSE' && $candidate->status === 'ACTIVE') {
                $candidateProfile = NurseProfile::query()->where('user_id', $candidate->id)->first();
                if ($candidateProfile && $candidateProfile->verified) {
                    $visibility = 'TARGETED';
                    $targetNurseUserId = $candidate->id;
                }
            }
        }

        $new = CareRequest::query()->create([
            'patient_user_id' => $user->id,
            'nurse_user_id' => null,

            'visibility' => $visibility,
            'target_nurse_user_id' => $visibility === 'TARGETED' ? $targetNurseUserId : null,
            'expires_at' => $payload['expires_at'] ?? null,

            'care_type' => $careRequest->care_type,
            'description' => $careRequest->description,
            'scheduled_at' => $payload['scheduled_at'] ?? null,

            'address' => $careRequest->address,
            'city' => $careRequest->city,
            'lat' => $careRequest->lat,
            'lng' => $careRequest->lng,

            'status' => 'PENDING',
        ]);

        Audit::log($user, 'CARE_REQUEST_REBOOKED', 'CareRequest', $new->id, [
            'visibility' => $new->visibility ?? null,
            'city' => $new->city ?? null,
        ], $request);

        // Notify patient (self)
        $user->notify(new CareRequestNotification(
            event: 'CREATED',
            careRequest: $new,
            message: $visibility === 'TARGETED'
                ? "Votre demande a été renvoyée à l'infirmier sélectionné."
                : "Votre demande a été renvoyée aux infirmiers proches."
        ));

        if ($visibility === 'TARGETED') {
            User::query()->find($targetNurseUserId)
                ?->notify(new CareRequestNotification(
                    event: 'CREATED',
                    careRequest: $new,
                    message: "Nouvelle demande ciblée (redemandé)."
                ));
        } else {
            NotifyNearByNursesOfNewCareRequest::dispatch($new->id);
        }

        return response()->json(['data' => new CareRequestResource($new)], 201);
    }
}
