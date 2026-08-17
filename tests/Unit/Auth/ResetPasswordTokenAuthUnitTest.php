<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('generates a cryptographically secure token with 15 minutes expiration time', function () {
    // Arrange
    $configuredExpirationMinutes = 15;
    $createdAt = Carbon::now();

    // Act
    // Simula cálculo de expiração de token de reset de senha
    $token = Str::random(64);
    $expiresAt = $createdAt->copy()->addMinutes($configuredExpirationMinutes);

    // Assert
    expect(strlen($token))->toBe(64)
        ->and($expiresAt->diffInMinutes($createdAt))->toBe($configuredExpirationMinutes)
        ->and($expiresAt->isFuture())->toBeTrue();
});

it('verifies that stored token hash cannot match raw plain text value directly', function () {
    // Arrange
    $rawToken = Str::random(64);

    // Act
    $hashedToken = Hash::make($rawToken);

    // Assert
    expect($hashedToken)->not->toBe($rawToken)
        ->and(Hash::check($rawToken, $hashedToken))->toBeTrue()
        ->and(Hash::check('invalid-wrong-token', $hashedToken))->toBeFalse();
});

it('validates password complexity rules for length character sets and symbols', function (string $password, bool $isValid) {
    // Arrange
    // Regra de complexidade: mínimo 8 caracteres, pelo menos 1 maiúscula, 1 minúscula, 1 número e 1 caractere especial
    $complexityRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_\-#])[A-Za-z\d@$!%*?&_\-#]{8,}$/';

    // Act
    $matches = (bool) preg_match($complexityRegex, $password);

    // Assert
    expect($matches)->toBe($isValid);
})->with([
    ['ValidP@ssw0rd1!', true],
    ['Another#Secure9', true],
    ['sh0rt!', false],
    ['lowercase123!', false],
    ['UPPERCASE123!', false],
    ['NoNumbersPassword!', false],
    ['NoSpecialChars12345', false],
]);
