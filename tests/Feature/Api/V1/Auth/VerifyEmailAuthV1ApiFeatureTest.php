<?php

use App\Enums\HttpStatusCodeEnum as Status;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->createVerificationToken = function (User $user, $expiresAt = null) {
        $plainToken = bin2hex(random_bytes(32));

        EmailVerification::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt ?? now()->addMinutes(30),
        ]);

        return $plainToken;
    };
});

// ============================================================
// Fluxo principal
// ============================================================

it('should confirm email with valid token and return 200', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertStatus(Status::SUCCESS_OK->value);
});

it('should mark email_verified_at in database after confirmation', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('should return success message in response body after confirmation', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertJsonStructure(['message']);
    expect($response->json('message'))->toBe('Email verified successfully.');
});

it('should verify email without authentication', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertStatus(Status::SUCCESS_OK->value);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('should soft delete token record after confirmation ensuring single-use', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]))
        ->assertStatus(Status::SUCCESS_OK->value);

    $this->assertSoftDeleted('email_verifications', [
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
    ]);
});

it('should dispatch Verified event on successful confirmation', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    Event::assertDispatched(Verified::class, function (Verified $event) use ($user) {
        return $event->user->id === $user->id;
    });
});

// ============================================================
// Validade do Token
// ============================================================

it('should return 400 when token query param is missing', function () {
    $response = $this->getJson(route('api.v1.auth.verify-email'));

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('Token is required.');
});

it('should return 400 when using invalid token', function () {
    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => 'invalid_random_token']));

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('Invalid or expired verification token.');
});

it('should return 400 when using expired token', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user, now()->subMinutes(10));

    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('Invalid or expired verification token.');
});

it('should return 400 when attempting to reuse a token (single-use)', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    // First attempt - succeeds
    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]))
        ->assertStatus(Status::SUCCESS_OK->value);

    // Second attempt with same token - fails
    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('Invalid or expired verification token.');
});

// ============================================================
// Estado do usuário
// ============================================================

it('should return 400 when user has already verified email', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = ($this->createVerificationToken)($user);

    $response = $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('This e-mail is already verified.');
});

it('should not overwrite email_verified_at when confirmation is attempted on already verified email', function () {
    $originalDate = now()->subDays(2)->startOfSecond();
    $user = User::factory()->create(['email_verified_at' => $originalDate]);
    $token = ($this->createVerificationToken)($user);

    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    expect($user->fresh()->email_verified_at->format('Y-m-d H:i:s'))
        ->toBe($originalDate->format('Y-m-d H:i:s'));
});

it('should set email_verified_at to a recent timestamp after confirmation', function () {
    $user = User::factory()->unverified()->create();
    $token = ($this->createVerificationToken)($user);

    $this->getJson(route('api.v1.auth.verify-email', ['token' => $token]));

    expect($user->fresh()->email_verified_at)
        ->not->toBeNull()
        ->and($user->fresh()->email_verified_at->isAfter(now()->subMinute()))->toBeTrue();
});
