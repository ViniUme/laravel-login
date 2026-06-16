<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Enums\HttpStatusCodeEnum as Status;
use Illuminate\Auth\Notifications\VerifyEmail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resendUrl = route('api.v1.auth.resend-confirmation-email');

    $this->generateValidConfirmUrl = function (User $user, $expiration = null) {
        return URL::temporarySignedRoute(
            'api.v1.auth.confirm-email',
            $expiration ?? now()->addMinutes(60),
            [
                'id'   => $user->getKey(),
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

// ============================================================
// Validade do token (URL Assinada)
// ============================================================

it('should return error when using invalid token (invalid signature)', function () {
    $user = User::factory()->unverified()->create();

    $validUrl   = ($this->generateValidConfirmUrl)($user);
    $invalidUrl = str_replace('signature=', 'signature=invalid', $validUrl);

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

// ============================================================
// Autenticação
// ============================================================

it('should return 401 when request is not authenticated', function () {
    $user       = User::factory()->unverified()->create();
    $confirmUrl = ($this->generateValidConfirmUrl)($user);

    $response = $this->getJson($confirmUrl);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

it('should return 403 when authenticated user tries to confirm another users email', function () {
    $user1 = User::factory()->unverified()->create();
    $user2 = User::factory()->unverified()->create();

    $urlForUser2 = ($this->generateValidConfirmUrl)($user2);

    $response = $this->actingAs($user1)->getJson($urlForUser2);

    $response->assertStatus(Status::CLIENT_ERROR_FORBIDDEN->value);
});

// ============================================================
// Reenvio do e-mail de confirmação
// ============================================================

it('should resend email when requested and user has not confirmed yet', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->postJson($this->resendUrl);

    $response->assertStatus(Status::SUCCESS_OK->value);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('should not resend email if already confirmed', function () {
    Notification::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson($this->resendUrl);

    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
    Notification::assertNothingSent();
});

it('should respect rate limit on resend', function () {
    $user = User::factory()->unverified()->create();

    // throttle:6,1 — consome as 6 tentativas permitidas
    foreach (range(1, 6) as $i) {
        $this->actingAs($user)->postJson($this->resendUrl);
    }

    // Sétima requisição deve estourar o limite
    $response = $this->actingAs($user)->postJson($this->resendUrl);

    $response->assertStatus(Status::CLIENT_ERROR_TOO_MANY_REQUESTS->value);
});

it('should ensure the resent email contains the valid signed url', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson($this->resendUrl);

    Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
        $mailData = $notification->toMail($user);

        return str_contains($mailData->actionUrl, 'signature=');
    });
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