<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */

    /**
     * @OA\Post(
     *   path="/register",
     *   tags={"Auth"},
     *   summary="Register a new user",
     *   description="Returns a Sanctum token (Bearer).",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"full_name","phone","password","role"},
     *       @OA\Property(property="full_name", type="string", example="John Doe"),
     *       @OA\Property(property="phone", type="string", example="22222222222"),
     *       @OA\Property(property="email", type="string", example=""),
     *     @OA\Property(property="password", type="string", example="password123"),
     *       @OA\Property(property="role", type="string", enum={"USER","ADMIN"}, example="USER")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *     @OA\JsonContent(
     *       @OA\Property(property="token_type", type="string", example="Bearer"),
     *       @OA\Property(property="access_token", type="string", example="1|xxxxxxxxxxxxxxxx"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="user", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'], // already normalized in request
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'status' => 'ACTIVE',
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'data' => [
                'user' => $user,
            ],
        ], 201);
    }
}
