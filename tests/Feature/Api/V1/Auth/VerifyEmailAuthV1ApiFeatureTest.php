<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Enums\HttpStatusCodeEnum as Status;
use Illuminate\Auth\Notifications\VerifyEmail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // $this->resendUrl = route('api.v1.auth.resend-confirmation-email');

    $this->generateValidConfirmUrl = function (User $user, $expiration = null) {
        return URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            $expiration ?? now()->addMinutes(5),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    };
});

// ============================================================
// Fluxo principal
// ============================================================

it('should confirm email with valid token and return 200', function () {
    $user = User::factory()->unverified()->create();

    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $response = $this->actingAs($user)->getJson($confirmUrl);

    $response->assertStatus(Status::SUCCESS_OK->value);
});

it('should mark email_verified_at in database after confirmation', function () {
    $user = User::factory()->unverified()->create();

    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $this->actingAs($user)->getJson($confirmUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('should return success message in response body after confirmation', function () {
    $user = User::factory()->unverified()->create();

    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $response = $this->actingAs($user)->getJson($confirmUrl);

    $response->assertJsonStructure(['message']);
    expect($response->json('message'))->not->toBeEmpty();
});

it('should verify email without authentication', function () {
    $user       = User::factory()->unverified()->create();
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $response = $this->getJson($confirmUrl);

    $response->assertStatus(Status::SUCCESS_OK->value);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

// ============================================================
// Validade do token (URL Assinada)
// ============================================================

it('should return error when using invalid token (invalid signature)', function () {
    $user = User::factory()->unverified()->create();

    $validUrl   = ($this->generateValidConfirmUrl)($user);
    $invalidUrl = preg_replace('/signature=[^&]+/', 'signature=invalidsignature', $validUrl);

    $response = $this->actingAs($user)->getJson($invalidUrl);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

it('should return error when using expired token (expired signature)', function () {
    $user = User::factory()->unverified()->create();

    $expiredUrl = ($this->generateValidConfirmUrl)($user, now()->subMinutes(10));

    $response = $this->actingAs($user)->getJson($expiredUrl);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

it('should return 400 when using a previously used token', function () {
    $user       = User::factory()->unverified()->create();
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    // Primeira confirmação — marca como verificado
    $this->actingAs($user)->getJson($confirmUrl);

    // Segunda tentativa com a mesma URL assinada
    $response = $this->actingAs($user)->getJson($confirmUrl);

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->not->toBeEmpty();
});

it('should return 403 when hash does not match users email', function () {
    $user = User::factory()->unverified()->create();
    $userWrong = User::factory()->unverified()->create(['email' => 'wrong@email.com']);

    $validUrl = ($this->generateValidConfirmUrl)($user);

    $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=' . sha1($userWrong->getEmailForVerification()), $validUrl);

    $response = $this->getJson($tamperedUrl);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

// ============================================================
// Vínculo do token com o usuário
// ============================================================

it('should return error when using another users token', function () {
    $user1 = User::factory()->unverified()->create();
    $user2 = User::factory()->unverified()->create();

    $urlForUser2 = ($this->generateValidConfirmUrl)($user2);

    $response = $this->actingAs($user1)->getJson($urlForUser2);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

it('should return 401 when token user no longer exists', function () {
    $user = User::factory()->unverified()->create();
    $url  = ($this->generateValidConfirmUrl)($user);

    // Remove o usuário — Sanctum não encontrará ninguém para autenticar
    $user->forceDelete();

    $response = $this->getJson($url);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

// ============================================================
// Estado do usuário
// ============================================================

it('should return 400 when attempting to confirm an already verified email', function () {
    $user       = User::factory()->create(['email_verified_at' => now()]);
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $response = $this->actingAs($user)->getJson($confirmUrl);

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    expect($response->json('message'))->not->toBeEmpty();
});

it('should not overwrite email_verified_at when confirmation is attempted on already verified email', function () {
    $originalDate = now()->subDays(2)->startOfSecond();
    $user         = User::factory()->create(['email_verified_at' => $originalDate]);
    $confirmUrl   = ($this->generateValidConfirmUrl)($user);

    // O controller retorna 400 antes de chamar markEmailAsVerified()
    $this->actingAs($user)->getJson($confirmUrl);

    expect($user->fresh()->email_verified_at->format('Y-m-d H:i:s'))
        ->toBe($originalDate->format('Y-m-d H:i:s'));
});

it('should set email_verified_at to a recent timestamp after confirmation', function () {
    $user       = User::factory()->unverified()->create();
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $this->getJson($confirmUrl);

    expect($user->fresh()->email_verified_at)
        ->not->toBeNull()
        ->and($user->fresh()->email_verified_at->isAfter(now()->subMinute()))->toBeTrue();
});

// ============================================================
// Autenticação
// ============================================================

it('should return 403 when authenticated user tries to confirm another users email', function () {
    $user1 = User::factory()->unverified()->create();
    $user2 = User::factory()->unverified()->create();

    $urlForUser2 = ($this->generateValidConfirmUrl)($user2);

    $response = $this->actingAs($user1)->getJson($urlForUser2);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

// ============================================================
// Integridade
// ============================================================

it('should not allow token reuse after successful confirmation', function () {
    $user       = User::factory()->unverified()->create();
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $this->actingAs($user)->getJson($confirmUrl)
        ->assertStatus(Status::SUCCESS_OK->value);

    $response = $this->actingAs($user)->getJson($confirmUrl);

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
});

it('should invalidate old signed url if the email changes', function () {
    $user   = User::factory()->unverified()->create(['email' => 'old@example.com']);
    $oldUrl = ($this->generateValidConfirmUrl)($user);

    $user->update(['email' => 'new@example.com']);

    $response = $this->actingAs($user)->getJson($oldUrl);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});