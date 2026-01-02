<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login returns consistent data envelope with 200 status', function () {
    $user = User::factory()->create([
        'phone' => '22200000002',
        'password' => Hash::make('password'),
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'phone' => '22200000002',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'token_type',
                'access_token',
                'user',
            ],
            'message',
        ]);

    expect($response->json('data.token_type'))->toBe('Bearer');
    expect($response->json('data.access_token'))->toBeString();
    expect($response->json('data.user.id'))->toBe($user->id);
});

test('logout returns message with 200 status', function () {
    $user = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/logout');

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

test('care request creation is rate limited to 10 per hour', function () {
    $user = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $requestData = [
        'care_type' => 'Test',
        'address' => 'Test Address',
        'city' => 'Nouakchott',
        'lat' => 18.1,
        'lng' => -15.9,
    ];

    // Make 10 successful requests
    for ($i = 0; $i < 10; $i++) {
        $response = $this->withToken($token)->postJson('/api/v1/care-requests', $requestData);
        $response->assertStatus(201);
    }

    // 11th request should be rate limited
    $response = $this->withToken($token)->postJson('/api/v1/care-requests', $requestData);
    $response->assertStatus(429);
});

test('favorites store returns message with 200 status', function () {
    $patient = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $nurse = User::factory()->create([
        'role' => 'NURSE',
        'status' => 'ACTIVE',
    ]);

    \App\Models\NurseProfile::create([
        'user_id' => $nurse->id,
        'verified' => true,
        'city' => 'Nouakchott',
    ]);

    $token = $patient->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/v1/favorites/{$nurse->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

test('favorites destroy returns message with 200 status', function () {
    $patient = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $nurse = User::factory()->create([
        'role' => 'NURSE',
        'status' => 'ACTIVE',
    ]);

    $token = $patient->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/v1/favorites/{$nurse->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

test('notifications mark read returns message with 200 status', function () {
    $user = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Create a test notification
    $notification = $user->notifications()->create([
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\CareRequestNotification',
        'data' => ['test' => 'data'],
        'read_at' => null,
    ]);

    $response = $this->withToken($token)->postJson("/api/v1/notifications/{$notification->id}/read");

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

test('notifications mark all read returns message with 200 status', function () {
    $user = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/notifications/read-all');

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

test('chat message sending is rate limited to 20 per minute', function () {
    $patient = User::factory()->create([
        'role' => 'PATIENT',
        'status' => 'ACTIVE',
    ]);

    $nurse = User::factory()->create([
        'role' => 'NURSE',
        'status' => 'ACTIVE',
    ]);

    // Create a care request
    $careRequest = \App\Models\CareRequest::create([
        'patient_user_id' => $patient->id,
        'nurse_user_id' => $nurse->id,
        'care_type' => 'Test',
        'address' => 'Test',
        'city' => 'Nouakchott',
        'lat' => 18.1,
        'lng' => -15.9,
        'status' => 'ACCEPTED',
    ]);

    $token = $patient->createToken('test')->plainTextToken;

    // Make 20 successful requests
    for ($i = 0; $i < 20; $i++) {
        $response = $this->withToken($token)->postJson("/api/v1/care-requests/{$careRequest->id}/messages", [
            'message' => "Test message $i"
        ]);
        $response->assertStatus(201);
    }

    // 21st request should be rate limited
    $response = $this->withToken($token)->postJson("/api/v1/care-requests/{$careRequest->id}/messages", [
        'message' => 'Test message 21'
    ]);
    $response->assertStatus(429);
});
