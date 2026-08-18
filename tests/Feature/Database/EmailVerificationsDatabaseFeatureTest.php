<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the email_verifications table exists', function () {
    expect(Schema::hasTable('email_verifications'))->toBeTrue();
});

it('verifies if the email_verifications table columns exist', function () {
    $columns = [
        'id',
        'user_id',
        'token_hash',
        'expires_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    expect(Schema::hasColumns('email_verifications', $columns))->toBeTrue();
});

it('verifies the email_verifications table column types', function () {
    expect(Schema::getColumnType('email_verifications', 'id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('email_verifications', 'user_id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('email_verifications', 'token_hash'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('email_verifications', 'expires_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('email_verifications', 'created_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('email_verifications', 'updated_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('email_verifications', 'deleted_at'))->toBeIn(['timestamp', 'datetime']);
});

it('verifies the email_verifications table column indexes', function () {
    $indexes = collect(Schema::getIndexes('email_verifications'));

    $primaryKey = $indexes->where('primary', true)->first();
    expect($primaryKey)->not->toBeNull()
        ->and($primaryKey['columns'])->toBe(['id']);

    $tokenHashIndex = $indexes->filter(function ($index) {
        return in_array('token_hash', $index['columns'] ?? []);
    })->first();
    expect($tokenHashIndex)->not->toBeNull();

    $foreignKeys = collect(Schema::getForeignKeys('email_verifications'));
    $userForeignKey = $foreignKeys->where('columns', ['user_id'])->first();

    expect($userForeignKey)->not->toBeNull()
        ->and($userForeignKey['foreign_table'])->toBe('users')
        ->and($userForeignKey['foreign_columns'])->toBe(['id']);
});
