<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the users table exists', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
});

it('verifies if the users table columns exist', function () {
    $columns = [
        'id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'is_active',
        'last_login_at',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    expect(Schema::hasColumns('users', $columns))->toBeTrue();
});

it('verifies the users table column types', function () {
    expect(Schema::getColumnType('users', 'id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('users', 'name'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('users', 'email'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('users', 'email_verified_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('users', 'password'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('users', 'is_active'))->toBeIn(['boolean', 'tinyint(1)'])
        ->and(Schema::getColumnType('users', 'last_login_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('users', 'remember_token'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('users', 'created_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('users', 'updated_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('users', 'deleted_at'))->toBeIn(['timestamp', 'datetime']);
});

it('verifies the users table column indexes', function () {
    $indexes = collect(Schema::getIndexes('users'));

    $primaryKey = $indexes->where('primary', true)->first();
    expect($primaryKey)->not->toBeNull()
        ->and($primaryKey['columns'])->toBe(['id']);

    $uniqueEmail = $indexes->where('unique', true)->where('columns', ['email'])->first();
    expect($uniqueEmail)->not->toBeNull();
});
