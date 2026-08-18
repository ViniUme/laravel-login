<?php

use App\Enums\HttpStatusCodeEnum as Status;
use App\Models\EmailVerification;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resendUrl = route('api.v1.auth.resend-verify-email');
});

it('should send verification email to unverified authenticated user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->postJson($this->resendUrl);

    $response->assertStatus(Status::SUCCESS_OK->value);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
    $this->assertDatabaseHas('email_verifications', [
        'user_id' => $user->id,
    ]);
});

it('should send verification email when unauthenticated user provides email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

    $response = $this->postJson($this->resendUrl, ['email' => 'unverified@example.com']);

    $response->assertStatus(Status::SUCCESS_OK->value);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('should return 400 when resend is requested for already verified user', function () {
    Notification::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson($this->resendUrl);

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->toBe('This e-mail is already verified.');

    Notification::assertNothingSent();
});

it('should return 200 without leaking account existence if email is not found', function () {
    Notification::fake();

    $response = $this->postJson($this->resendUrl, ['email' => 'nonexistent@example.com']);

    $response->assertStatus(Status::SUCCESS_OK->value);
    Notification::assertNothingSent();
});

it('should invalidate previous verification tokens when a new one is requested', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    // Create first token
    EmailVerification::factory()->create(['user_id' => $user->id]);
    expect(EmailVerification::where('user_id', $user->id)->count())->toBe(1);

    // Request new token
    $this->actingAs($user)->postJson($this->resendUrl)->assertStatus(Status::SUCCESS_OK->value);

    // Only 1 non-deleted token should remain
    expect(EmailVerification::where('user_id', $user->id)->count())->toBe(1);
    // Total including soft-deleted should be 2
    expect(EmailVerification::withTrashed()->where('user_id', $user->id)->count())->toBe(2);
});
