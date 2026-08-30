<?php

use App\Services\Auth\SecureAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

it('generates a valid dummy hash string and stores it in cache indefinitely', function () {
    Cache::flush();

    expect(Cache::has('auth:dummy_hash'))->toBeFalse();

    $dummyHash = SecureAuthService::getDummyHash();

    expect($dummyHash)->toBeString()
        ->and($dummyHash)->not->toBeEmpty()
        ->and(Cache::has('security:auth:dummy_hash'))->toBeTrue()
        ->and(Cache::get('security:auth:dummy_hash'))->toBe($dummyHash);
});

it('returns the same cached dummy hash on subsequent calls', function () {
    $firstCall = SecureAuthService::getDummyHash();
    $secondCall = SecureAuthService::getDummyHash();

    expect($firstCall)->toBe($secondCall);
});

it('validates safely with Hash::check against arbitrary strings', function () {
    $dummyHash = SecureAuthService::getDummyHash();

    expect(Hash::check('arbitrary-token-value', $dummyHash))->toBeFalse();
});
