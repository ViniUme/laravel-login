<?php

use App\Enums\HttpStatusCodeEnum as Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->signUpUrl = route('api.v1.auth.sign-up');
});

it('should return 201 and create user on valid sign up data', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ];

    $response = $this->postJson($this->signUpUrl, $payload);

    $response->assertStatus(Status::SUCCESS_CREATED->value)
        ->assertJsonStructure([
            'status',
            'message',
            'body' => [
                'access_token',
                'token_type'
            ]
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
    $response = $this->postJson($this->signUpUrl, []);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
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

    $response = $this->postJson($this->signUpUrl, $payload);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
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

    $response = $this->postJson($this->signUpUrl, $payload);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);
});

it('should return 422 if password is less than 8 characters', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => '1234567', // 7 chars long
        'password_confirmation' => '1234567',
    ];

    $response = $this->postJson($this->signUpUrl, $payload);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);
});

it('should return 422 if password confirmation does not match', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'differentPassword123',
    ];

    $response = $this->postJson($this->signUpUrl, $payload);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);
});

// ============================================================
// Token gerado e armazenado corretamente após cadastro bem-sucedido
// ============================================================

it('should persist access token in personal_access_tokens table after successful sign up', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::SUCCESS_CREATED->value);

    $user = User::where('email', 'john.doe@example.com')->first();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
    ]);

    expect($response->json('access_token'))->not->toBeNull()
        ->and($response->json('access_token'))->not->toBe('');
});

// ============================================================
// Token retornado no cadastro permite acesso a rotas protegidas
// ============================================================

it('should return 200 when accessing a protected route with token received on sign up', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::SUCCESS_CREATED->value);

    $token = $response->json('access_token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/user')
        ->assertStatus(Status::SUCCESS_OK->value);
});

// ============================================================
// Sanitização de inputs — SQL Injection
// ============================================================

it('should return 422 and not create user with sql injection in email field', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => "' OR '1'='1",
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);

    $this->assertDatabaseCount('users', 0);
});

it('should return 422 and not create user with sql injection in name field', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => "'; DROP TABLE users; --",
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $acceptedValues = [
        Status::SUCCESS_CREATED->value,
        Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value,
    ];

    expect($response->status())->toBeIn($acceptedValues);

    // Independente do status, a tabela users não pode ter sido destruída
    $this->assertDatabaseCount('users', $response->status() === Status::SUCCESS_CREATED->value ? 1 : 0);

    // Se o cadastro foi aceito, valida que o valor foi armazenado como string literal
    if ($response->status() === Status::SUCCESS_CREATED->value) {
        $this->assertDatabaseHas('users', [
            'name' => "'; DROP TABLE users; --",
        ]);
    }
});

it('should not authenticate or break database with sql injection in password field', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => "'; DROP TABLE users; --",
        'password_confirmation' => "'; DROP TABLE users; --",
    ]);

    // Deve rejeitar por não atender os requisitos mínimos de senha ou aceitar e armazenar com hash
    $acceptedValues = [
        Status::SUCCESS_CREATED->value,
        Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value,
    ];
    expect($response->status())->toBeIn($acceptedValues);
    expect($response->status())->not->toBe(Status::SERVER_ERROR_INTERNAL->value);

    // A tabela users não pode ter sido destruída
    expect(User::count())->toBeGreaterThanOrEqual(0);

    // Se o cadastro foi aceito, a senha deve estar armazenada como hash e não como texto puro
    if ($response->status() === Status::SUCCESS_CREATED->value) {
        $user = User::where('email', 'john.doe@example.com')->first();

        expect($user)->not->toBeNull();
        expect($user->password)->not->toBe("'; DROP TABLE users; --");
        expect(Hash::check("'; DROP TABLE users; --", $user->password))->toBeTrue();
    }
});

it('should not authenticate or break database with sql injection in password_confirmation field', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => "'; DROP TABLE users; --",
    ]);

    // A confirmação não bate com a senha, então deve rejeitar
    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['password']);

    $this->assertDatabaseCount('users', 0);
});

// ============================================================
// Validação de campos com apenas espaços em branco
// ============================================================

it('should return 422 if name contains only whitespace', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => '     ',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['name']);
});

it('should return 422 if email contains only whitespace', function () {
    $response = $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => '     ',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertStatus(Status::CLIENT_ERROR_UNPROCESSABLE_ENTITY->value)
        ->assertJsonValidationErrors(['email']);
});

// ============================================================
// Usuário criado com is_active = false por padrão
// ============================================================

it('should create user with is_active set to false by default', function () {
    $this->postJson($this->signUpUrl, [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ])->assertStatus(Status::SUCCESS_CREATED->value);

    $this->assertDatabaseHas('users', [
        'email' => 'john.doe@example.com',
        'is_active' => false,
    ]);
});
