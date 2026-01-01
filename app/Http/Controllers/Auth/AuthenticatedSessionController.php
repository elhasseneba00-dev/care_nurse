<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * @OA\Post(
     *   path="/login",
     *   tags={"Auth"},
     *   summary="Login with phone and password",
     *   description="Returns a Sanctum token (Bearer).",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"phone","password"},
     *       @OA\Property(property="phone", type="string", example="22222222222"),
     *       @OA\Property(property="password", type="string", example="password123")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="token_type", type="string", example="Bearer"),
     *       @OA\Property(property="access_token", type="string", example="1|xxxxxxxxxxxxxxxx"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="user", type="object")
     *       ),
     *       @OA\Property(property="message", type="string")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Invalid credentials / validation error"),
     *   @OA\Response(response=403, description="Account not active")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $phone = Phone::normalize($credentials['phone']);

        /** @var User|null $user */
        $user = User::query()
            ->where('phone', $phone)
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'message' => 'Account is not active.',
            ], 403);
        }

        // Optional: revoke old tokens (1 session at a time)
        $user->tokens()->delete();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'data' => [
                'user' => $user,
            ],
            "message" => "As a $user->role, u've successfully connected.",
        ]);
    }

    /**
     * @OA\Post(
     *   path="/logout",
     *   tags={"Auth"},
     *   summary="Logout (revoke current token)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=204, description="Logged out")
     * )
     */    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            "message" => "User have successfully disconnected.",
        ], 204);
    }
}
