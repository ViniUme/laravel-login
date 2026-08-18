<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecureAuthService
{
    private const string CACHE_KEY = 'security:auth:dummy_hash';

    /**
     * Retorna o dummy hash do cache ou gera um novo por tempo indeterminado caso não exista.
     */
    public static function getDummyHash(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): string {
            return Hash::make(Str::random(32));
        });
    }
}
