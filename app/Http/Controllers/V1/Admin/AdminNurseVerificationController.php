<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NurseProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNurseVerificationController extends Controller
{
    public  function  index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verified' => ['nullable', 'boolean'],
        ]);

        $verified = $validated['verified'] ?? null;

        $query = NurseProfile::query();

        if (!is_null($verified)) {
            $query->where('verified', (bool) $verified);
        }

        $profiles = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json([
            'data' => $profiles->items(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    public function verify(Request $request, int $nurseUserId): JsonResponse
    {
        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $user = User::query()->findOrFail($nurseUserId);

        if ($user->role !== 'NURSE') {
            return response()->json(['message' => 'User is not a nurse.'], 422);
        }
        $profile = NurseProfile::query()->where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['message' => 'Nurse profile not found.'], 404);
        }

        $profile->update(['verified' => (bool) $validated['verified']]);

        return response()->json([
            'data' => $profile->fresh(),
        ]);
    }
}
