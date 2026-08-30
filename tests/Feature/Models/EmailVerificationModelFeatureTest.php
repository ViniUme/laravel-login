<?php

use App\Models\EmailVerification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates an email verification successfully with valid data and generates a valid uuid', function () {
    // Arrange & Act
    $user = User::factory()->create();
    $verification = EmailVerification::factory()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'plain_test_token'),
        'expires_at' => now()->addMinutes(30),
    ]);

    // Assert
    expect($verification)->toBeInstanceOf(EmailVerification::class)
        ->and(Str::isUuid($verification->id))->toBeTrue()
        ->and($verification->expires_at)->toBeInstanceOf(CarbonInterface::class);

    $this->assertDatabaseHas('email_verifications', [
        'id' => $verification->id,
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'plain_test_token'),
    ]);
});

it('belongs to a user', function () {
    // Arrange
    $user = User::factory()->create();
    $verification = EmailVerification::factory()->create(['user_id' => $user->id]);

    // Act & Assert
    expect($verification->user)->toBeInstanceOf(User::class)
        ->and($verification->user->id)->toBe($user->id);
});

it('updates an email verification record successfully', function () {
    // Arrange
    $verification = EmailVerification::factory()->create();
    $newHash = hash('sha256', 'updated_plain_token');

    // Act
    $verification->update(['token_hash' => $newHash]);

    // Assert
    expect($verification->fresh()->token_hash)->toBe($newHash);
});

it('soft deletes an email verification successfully', function () {
    // Arrange
    $verification = EmailVerification::factory()->create();

    // Act
    $verification->delete();

    // Assert
    $this->assertSoftDeleted('email_verifications', ['id' => $verification->id]);
});
