<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\HttpStatusCodeEnum as Status;

uses(RefreshDatabase::class);

// ============================================================
// Validação de presença de campos obrigatórios
// ============================================================

it('should return 422 if email field is missing', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'password' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);
});

it('should return 422 if usinfield is missing', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);
});

// ============================================================
// Validação de formato do e-mail
// ============================================================

it('should return 422 if email format is invalid', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'invalid-email-format.com',
        'password' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);
});

// ============================================================
// Validação de hash/senha
// ============================================================

it('should validate password using bcrypt hash comparison and not plain text', function () {
    $plainPassword = 'secretPassword123';

    $user = User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
    ]);

    // Confirma que o hash armazenado NÃO é igual ao texto puro
    expect($user->password)->not->toBe($plainPassword);

    // Confirma que o Hash::check valida corretamente a senha
    expect(Hash::check($plainPassword, $user->password))->toBeTrue();

    // Confirma que uma senha errada falha na comparação
    expect(Hash::check('wrongPassword', $user->password))->toBeFalse();
});

// ============================================================
// Autenticação com credenciais válidas
// ============================================================

it('should return 200 with token and user data on valid credentials', function () {
    $plainPassword = 'secretPassword123';

    User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => $plainPassword,
    ]);

    $response->assertStatus(Status::SUCCESS_OK->value)
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

    // O token não deve ser nulo ou vazio
    expect($response->json('access_token'))->not->toBeNull()
        ->and($response->json('access_token'))->not->toBe('');

    // A senha não deve ser exposta na resposta
    expect($response->json('user'))->not->toHaveKey('password')
        ->and($response->json())->not->toHaveKey('password');
});

// ============================================================
// Usuário não cadastrado
// ============================================================

it('should return 401 when user does not exist', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'nonexistent@example.com',
        'password' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

// ============================================================
// Senha incorreta
// ============================================================

it('should return 401 when password is incorrect', function () {
    User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('correctPassword123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => 'wrongPassword456',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

// ============================================================
// Mensagens de erro genéricas e idênticas (segurança)
// ============================================================

it('should return identical generic error message for nonexistent user and wrong password', function () {
    User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('correctPassword123'),
        'is_active' => true,
    ]);

    $responseNonexistent = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'ghost@example.com',
        'password' => 'anyPassword123',
    ]);

    $responseWrongPassword = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => 'wrongPassword456',
    ]);

    $responseNonexistent->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
    $responseWrongPassword->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);

    // As mensagens de erro devem ser idênticas para não vazar informação de enumeração
    expect($responseNonexistent->json('message'))
        ->toBe($responseWrongPassword->json('message'));
});

// ============================================================
// Bloqueio de usuários inativos / banidos
// ============================================================

it('should return 401 when user account is inactive', function () {
    $plainPassword = 'secretPassword123';

    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'inactive@example.com',
        'password' => $plainPassword,
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

it('should return 401 when user account is soft deleted (banned)', function () {
    $plainPassword = 'secretPassword123';

    $user = User::factory()->create([
        'email' => 'banned@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
    ]);

    $user->delete();

    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'banned@example.com',
        'password' => $plainPassword,
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

// ============================================================
// Rate Limiting
// ============================================================

it('should block requests after too many consecutive failed login attempts', function () {
    User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('correctPassword123'),
        'is_active' => true,
    ]);

    // Dispara múltiplas tentativas com senha errada até ultrapassar o limite
    $maxAttempts = 5;

    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->postJson('/api/v1/auth/sign-in', [
            'email' => 'john.doe@example.com',
            'password' => 'wrongPassword',
        ]);
    }

    // A próxima requisição deve ser bloqueada por rate limiting
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => 'wrongPassword',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_TOO_MANY_REQUESTS->value);
});

// ============================================================
// Sanitização de inputs — SQL Injection e caracteres maliciosos
// ============================================================

it('should not authenticate with sql injection attempt in email field', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => "' OR '1'='1",
        'password' => 'anyPassword123',
    ]);

    // Deve falhar na validação de formato ou retornar não autorizado — nunca 200
    expect($response->status())->not->toBe(Status::SUCCESS_OK->value);
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value);
});

it('should not authenticate with malicious characters in password field', function () {
    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => "'; DROP TABLE users; --",
    ]);

    // Deve retornar 401 ou 422 — nunca 200
    $acceptedCodes = [
        Status::CLIENT_ERROR_UNAUTHORIZED->value,
        Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value
    ];
    expect($response->status())->toBeIn($acceptedCodes);
    expect($response->status())->not->toBe(Status::SUCCESS_OK->value);
});

// ============================================================
// Token gerado e armazenado corretamente após login bem-sucedido
// ============================================================

it('should persist access token in personal_access_tokens table after successful login', function () {
    $plainPassword = 'secretPassword123';

    $user = User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => $plainPassword,
    ]);

    $response->assertStatus(Status::SUCCESS_OK->value);

    // Valida que o token foi de fato salvo no banco
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
    ]);

    // O token retornado não deve ser vazio
    expect($response->json('access_token'))->not->toBeNull()
        ->and($response->json('access_token'))->not->toBe('');
});

// ============================================================
// Redirecionamento de usuários não autenticados em rotas protegidas
// ============================================================

it('should return 401 when unauthenticated user tries to access a protected route', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(Status::CLIENT_ERROR_UNAUTHORIZED->value);
});

it('should return 200 when authenticated user accesses a protected route with valid token', function () {
    $plainPassword = 'secretPassword123';

    $user = User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make($plainPassword),
        'is_active' => true,
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/sign-in', [
        'email' => 'john.doe@example.com',
        'password' => $plainPassword,
    ]);

    $loginResponse->assertStatus(Status::SUCCESS_OK->value);

    $token = $loginResponse->json('access_token');

    $protectedResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/user');

    $protectedResponse->assertStatus(Status::SUCCESS_OK->value);
});
