<?php

use App\Http\Controllers\V1\Admin\AdminNurseVerificationController;
use App\Http\Controllers\V1\Care\CareRequestController;
use App\Http\Controllers\V1\Chat\MessageController;
use App\Http\Controllers\V1\Nurse\NurseProfileController;
use App\Http\Controllers\V1\Nurse\NurseSearchController;
use App\Http\Controllers\V1\Patient\PatientProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    require __DIR__.'/auth.php';

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', function (Request $request) {
            return response()->json(['data' => $request->user()]);
        });

        Route::get('/patient/profile', [PatientProfileController::class, 'show']);
        Route::put('/patient/profile', [PatientProfileController::class, 'upsert']);

        Route::get('/nurse/profile', [NurseProfileController::class, 'show']);
        Route::put('/nurse/profile', [NurseProfileController::class, 'upsert']);

        Route::get('/nurses/search', [NurseSearchController::class, 'search']);

        // Care requests
        Route::post('/care-requests', [CareRequestController::class, 'store']);
        Route::get('/care-requests', [CareRequestController::class, 'index']);
        Route::get('/care-requests/{careRequest}', [CareRequestController::class, 'show']);

        Route::post('/care-requests/{careRequest}/accept', [CareRequestController::class, 'accept']);
        Route::post('/care-requests/{careRequest}/reject', [CareRequestController::class, 'reject']);
        Route::post('/care-requests/{careRequest}/cancel', [CareRequestController::class, 'cancel']);
        Route::post('/care-requests/{careRequest}/complete', [CareRequestController::class, 'complete']);

        // NEW: ignore open request (nurse only)
        Route::post('/care-requests/{careRequest}/ignore', [CareRequestController::class, 'ignore']);

        // Chat
        Route::get('/care-requests/{careRequest}/messages', [MessageController::class, 'index']);
        Route::post('/care-requests/{careRequest}/messages', [MessageController::class, 'store']);

        Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
            Route::get('/nurses', [AdminNurseVerificationController::class, 'index']);
            Route::post('/nurses/{nurseUserId}/verify', [AdminNurseVerificationController::class, 'verify']);
        });
    });
});
