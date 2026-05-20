<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the role_user table exists', function () {
    expect(Schema::hasTable('role_user'))->toBeTrue();
});

it('verifies if the role_user table columns exist', function () {
    $columns = [
        'role_id',
        'user_id',
    ];

    expect(Schema::hasColumns('role_user', $columns))->toBeTrue();
});

it('verifies the role_user table column types', function () {
    expect(Schema::getColumnType('role_user', 'role_id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('role_user', 'user_id'))->toBeIn(['uuid', 'varchar', 'string']);
});

it('verifies the role_user table column indexes', function () {
    $foreignKeys = collect(Schema::getForeignKeys('role_user'));

    $roleForeignKey = $foreignKeys->where('columns', ['role_id'])->first();
    expect($roleForeignKey)->not->toBeNull()
        ->and($roleForeignKey['foreign_table'])->toBe('roles')
        ->and($roleForeignKey['foreign_columns'])->toBe(['id']);

    $userForeignKey = $foreignKeys->where('columns', ['user_id'])->first();
    expect($userForeignKey)->not->toBeNull()
        ->and($userForeignKey['foreign_table'])->toBe('users')
        ->and($userForeignKey['foreign_columns'])->toBe(['id']);
});
