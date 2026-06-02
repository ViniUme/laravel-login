<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('should return 201 and create user on valid sign up data', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ];

    $response = $this->postJson('/api/v1/auth/sign-up', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
            ],
            'access_token',
        ]);

    // Validate that the password is NOT exposed in the JSON response
    expect($response->json('user'))->not->toHaveKey('password')
        ->and($response->json())->not->toHaveKey('password');

    // Validate that the record was saved in the database
    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
    ]);

    // Validate that the password was hashed correctly and not stored as plain text
    $user = User::where('email', 'john.doe@example.com')->first();
    expect(Hash::check('secretPassword123', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('secretPassword123');
});

it('should return 422 if required fields are missing', function () {
    $response = $this->postJson('/api/v1/auth/sign-up', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name',
            'email',
            'password',
        ]);
});

it('should return 422 if email format is invalid', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'invalid-email-format.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ];

    $response = $this->postJson('/api/v1/auth/sign-up', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('should return 422 if email is already in use', function () {
    // Creating a user in the database to simulate an existing account
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $payload = [
        'name' => 'Another User',
        'email' => 'existing@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ];

    $response = $this->postJson('/api/v1/auth/sign-up', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('should return 422 if password is less than 8 characters', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => '1234567', // 7 chars long
        'password_confirmation' => '1234567',
    ];

    $response = $this->postJson('/api/v1/auth/sign-up', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('should return 422 if password confirmation does not match', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'differentPassword123',
    ];

    $response = $this->postJson('/api/v1/auth/sign-up', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
