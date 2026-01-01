<?php

namespace App\OpenApi;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     title="CareNear API",
 *     version="1.0.0",
 *     description="CareNear backend API (Laravel). Dev docs only."
 *   ),
 *   @OA\Server(
 *     url="/api/v1",
 *     description="Local (Herd) base path"
 *   )
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT",
 *   description="Use Sanctum token as Bearer token"
 * )
 */
class OpenApi {}
