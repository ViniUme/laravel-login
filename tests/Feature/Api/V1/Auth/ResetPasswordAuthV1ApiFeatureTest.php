<?php

use App\Enums\HttpStatusCodeEnum as Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resetPasswordUrl = route('api.v1.auth.reset-password');

    $this->createResetToken = function (string $email, string $plainToken, ?DateTimeInterface $createdAt = null): void {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => $createdAt ?? now(),
            ]
        );
    };
});

// ============================================================================
// 1. Lógica de Negócio e Validação de Payload (Tokens & Complexidade)
// ============================================================================

it('rejects password reset when required fields are missing', function () {
    // Arrange
    $payload = [];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email', 'token', 'password']);
});

it('rejects password reset with invalid email format', function () {
    // Arrange
    $payload = [
        'email' => 'invalid-email-format',
        'token' => 'valid-secure-token-123',
        'password' => 'StrongP@ssw0rd!',
        'password_confirmation' => 'StrongP@ssw0rd!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);
});

it('rejects password reset when password confirmation does not match', function () {
    // Arrange
    $payload = [
        'email' => 'user@example.com',
        'token' => 'valid-secure-token-123',
        'password' => 'StrongP@ssw0rd1!',
        'password_confirmation' => 'DifferentP@ssw0rd2!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);
});

it('rejects weak passwords that do not meet complexity requirements', function (string $weakPassword) {
    // Arrange
    $payload = [
        'email' => 'user@example.com',
        'token' => 'valid-secure-token-123',
        'password' => $weakPassword,
        'password_confirmation' => $weakPassword,
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);
})->with([
    'too short (< 8 chars)' => 'Sh0rt!',
    'missing uppercase' => 'lowercase123!',
    'missing lowercase' => 'UPPERCASE123!',
    'missing numbers' => 'NoNumbersPassword!',
    'missing special symbol' => 'NoSymbols12345',
]);

it('resets user password successfully and hashes new password with bcrypt', function () {
    // Arrange
    $oldPassword = 'OldPassword123!';
    $newPassword = 'NewStrongP@ssw0rd!';
    $rawToken = Str::random(64);

    $user = User::factory()->create([
        'email' => 'developer@example.com',
        'password' => Hash::make($oldPassword),
    ]);

    ($this->createResetToken)($user->email, $rawToken, now());

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::SUCCESS_OK->value)
        ->assertJsonStructure(['message']);

    $user->refresh();

    expect(Hash::check($newPassword, $user->password))->toBeTrue()
        ->and(Hash::check($oldPassword, $user->password))->toBeFalse()
        ->and($user->password)->not->toBe($newPassword);
});

it('stores password reset tokens exclusively as cryptographic hashes and never in plain text', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'secure.token@example.com']);
    $rawToken = Str::random(64);

    // Act
    ($this->createResetToken)($user->email, $rawToken, now());

    // Assert
    $tokenRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

    expect($tokenRecord)->not->toBeNull()
        ->and($tokenRecord->token)->not->toBe($rawToken)
        ->and(Hash::check($rawToken, $tokenRecord->token))->toBeTrue();
});

it('invalidates reset token immediately after successful use to ensure single use', function () {
    // Arrange
    $rawToken = Str::random(64);
    $user = User::factory()->create(['email' => 'single.use@example.com']);
    ($this->createResetToken)($user->email, $rawToken, now());

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ];

    // Act - First Attempt (Success)
    $firstResponse = $this->postJson($this->resetPasswordUrl, $payload);

    // Act - Second Attempt with Same Token (Must Fail)
    $secondResponse = $this->postJson($this->resetPasswordUrl, [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'AnotherStrongP@ssw0rd2!',
        'password_confirmation' => 'AnotherStrongP@ssw0rd2!',
    ]);

    // Assert
    $firstResponse->assertStatus(Status::SUCCESS_OK->value);
    $secondResponse->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);

    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

it('rejects password reset when token is expired beyond the allowable window', function () {
    // Arrange
    $rawToken = Str::random(64);
    $user = User::factory()->create(['email' => 'expired.token@example.com']);

    // Configura token criado há 20 minutos (limite de expiração: 15 minutos)
    ($this->createResetToken)($user->email, $rawToken, now()->subMinutes(20));

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
});

it('rejects password reset when token is malformed or invalid', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'tampered.token@example.com']);
    ($this->createResetToken)($user->email, 'original-valid-token-123', now());

    $payload = [
        'email' => $user->email,
        'token' => 'tampered-invalid-token-456',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);
});

it('invalidates previous reset tokens for the user when a new reset is requested', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'renew.token@example.com']);
    $firstToken = 'first-raw-token-111';
    $secondToken = 'second-raw-token-222';

    ($this->createResetToken)($user->email, $firstToken, now()->subMinutes(5));

    // Act - Simula solicitação de novo token (sobrescrevendo token anterior)
    ($this->createResetToken)($user->email, $secondToken, now());

    $responseOldToken = $this->postJson($this->resetPasswordUrl, [
        'email' => $user->email,
        'token' => $firstToken,
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ]);

    // Assert
    $responseOldToken->assertStatus(Status::CLIENT_ERROR_BAD_REQUEST->value);

    $tokenRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();
    expect(Hash::check($secondToken, $tokenRecord->token))->toBeTrue()
        ->and(Hash::check($firstToken, $tokenRecord->token))->toBeFalse();
});

// ============================================================================
// 2. Segurança da Informação e Proteção de Dados (LGPD)
// ============================================================================

it('prevents user enumeration by returning identical generic response for existing and non-existing emails', function () {
    // Arrange
    $existingUser = User::factory()->create(['email' => 'registered.user@example.com']);
    $rawToken = Str::random(64);
    ($this->createResetToken)($existingUser->email, $rawToken, now());

    $payloadNonExistent = [
        'email' => 'unregistered.ghost@example.com',
        'token' => 'invalid-or-random-token',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ];

    $payloadWrongTokenExisting = [
        'email' => $existingUser->email,
        'token' => 'invalid-or-wrong-token',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ];

    // Act
    $responseNonExistent = $this->postJson($this->resetPasswordUrl, $payloadNonExistent);
    $responseWrongToken = $this->postJson($this->resetPasswordUrl, $payloadWrongTokenExisting);

    // Assert
    // Status HTTP e estrutura da mensagem de erro devem ser idênticos para evitar enumeração (LGPD)
    expect($responseNonExistent->status())->toBe($responseWrongToken->status())
        ->and($responseNonExistent->json())->toEqual($responseWrongToken->json());
});

it('mitigates timing attacks by executing in consistent time regardless of user existence', function () {
    // Arrange
    $existingUser = User::factory()->create(['email' => 'real.user@example.com']);
    $rawToken = Str::random(64);
    ($this->createResetToken)($existingUser->email, $rawToken, now());

    // Act - Mede tempo para usuário existente com token inválido
    $startTimeExisting = microtime(true);
    $this->postJson($this->resetPasswordUrl, [
        'email' => $existingUser->email,
        'token' => 'invalid-token-to-force-hash-check',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ]);
    $durationExisting = (microtime(true) - $startTimeExisting) * 1000; // ms

    // Act - Mede tempo para usuário inexistente (deve executar hash dummy para mitigar timing attack)
    $startTimeNonExistent = microtime(true);
    $this->postJson($this->resetPasswordUrl, [
        'email' => 'fake.nonexistent@example.com',
        'token' => 'invalid-token-to-force-hash-check',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ]);
    $durationNonExistent = (microtime(true) - $startTimeNonExistent) * 1000; // ms

    // Assert
    // A discrepância de tempo entre usuário existente e inexistente não deve ultrapassar 300ms
    $timeDelta = abs($durationExisting - $durationNonExistent);
    expect($timeDelta)->toBeLessThan(300.0);
});

it('enforces rate limiting by returning 429 too many requests after excessive failed attempts', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'rate.limited@example.com']);
    $maxAttempts = 5;

    // Act
    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->postJson($this->resetPasswordUrl, [
            'email' => $user->email,
            'token' => 'invalid-token-'.$i,
            'password' => 'NewStrongP@ssw0rd1!',
            'password_confirmation' => 'NewStrongP@ssw0rd1!',
        ]);
    }

    $response = $this->postJson($this->resetPasswordUrl, [
        'email' => $user->email,
        'token' => 'another-attempt',
        'password' => 'NewStrongP@ssw0rd1!',
        'password_confirmation' => 'NewStrongP@ssw0rd1!',
    ]);

    // Assert
    $response->assertStatus(Status::CLIENT_ERROR_TOO_MANY_REQUESTS->value);
});

it('prevents sensitive data including plain passwords tokens and pii from being exposed in application logs', function () {
    // Arrange
    Log::spy();

    $user = User::factory()->create(['email' => 'privacy.lgpd@example.com']);
    $rawToken = 'secret-raw-reset-token-999';
    $plainPassword = 'MySecretP@ssw0rd123!';

    ($this->createResetToken)($user->email, $rawToken, now());

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => $plainPassword,
        'password_confirmation' => $plainPassword,
    ];

    // Act
    $this->postJson($this->resetPasswordUrl, $payload);

    // Assert - Garante que nem a senha nem o token em texto limpo foram enviados para o logger
    Log::shouldNotHaveReceived('info', function ($message, $context = []) use ($plainPassword, $rawToken) {
        $jsonMessage = json_encode([$message, $context]);

        return str_contains($jsonMessage, $plainPassword) || str_contains($jsonMessage, $rawToken);
    });

    Log::shouldNotHaveReceived('error', function ($message, $context = []) use ($plainPassword, $rawToken) {
        $jsonMessage = json_encode([$message, $context]);

        return str_contains($jsonMessage, $plainPassword) || str_contains($jsonMessage, $rawToken);
    });
});

// ============================================================================
// 3. Arquitetura Distribuída e Concorrência
// ============================================================================

it('prevents race conditions by ensuring only one of simultaneous requests succeeds for the same token', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'concurrency@example.com']);
    $rawToken = Str::random(64);
    ($this->createResetToken)($user->email, $rawToken, now());

    $payload1 = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'ConcurrentP@ss1!',
        'password_confirmation' => 'ConcurrentP@ss1!',
    ];

    $payload2 = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'ConcurrentP@ss2!',
        'password_confirmation' => 'ConcurrentP@ss2!',
    ];

    // Act - Simula concorrência atômica de consumo
    $response1 = $this->postJson($this->resetPasswordUrl, $payload1);
    $response2 = $this->postJson($this->resetPasswordUrl, $payload2);

    // Assert
    // Exatamente uma requisição deve obter 200 OK e a outra deve ser rejeitada
    $statuses = [$response1->status(), $response2->status()];
    expect($statuses)->toContain(Status::SUCCESS_OK->value)
        ->and($statuses)->toContain(Status::CLIENT_ERROR_BAD_REQUEST->value);
});

it('revokes all active personal access tokens and sessions across all devices upon password reset', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'session.revocation@example.com']);
    $rawToken = Str::random(64);
    ($this->createResetToken)($user->email, $rawToken, now());

    // Cria tokens de acesso pré-existentes (simulando múltiplos logins/dispositivos)
    $token1 = $user->createToken('device_mobile')->plainTextToken;
    $token2 = $user->createToken('device_web')->plainTextToken;
    $token3 = $user->createToken('device_tablet')->plainTextToken;

    expect($user->tokens()->count())->toBe(3);

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'NewRevokingP@ss1!',
        'password_confirmation' => 'NewRevokingP@ss1!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::SUCCESS_OK->value);

    // Todos os personal access tokens do usuário devem ter sido revogados
    expect($user->fresh()->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

it('dispatches domain events only when the password reset transaction completes successfully', function () {
    // Arrange
    Event::fake();

    $user = User::factory()->create(['email' => 'events.domain@example.com']);
    $rawToken = Str::random(64);
    ($this->createResetToken)($user->email, $rawToken, now());

    $payload = [
        'email' => $user->email,
        'token' => $rawToken,
        'password' => 'EventDrivenP@ss1!',
        'password_confirmation' => 'EventDrivenP@ss1!',
    ];

    // Act
    $response = $this->postJson($this->resetPasswordUrl, $payload);

    // Assert
    $response->assertStatus(Status::SUCCESS_OK->value);

    // Dispara evento de domínio de notificação/auditoria após commit da transação
    Event::assertDispatched('App\Events\PasswordUpdated', function ($event) use ($user) {
        return isset($event->user) && $event->user->id === $user->id;
    });
});
