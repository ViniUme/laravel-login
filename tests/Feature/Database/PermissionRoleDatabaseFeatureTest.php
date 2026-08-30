<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the permission_role table exists', function () {
    expect(Schema::hasTable('permission_role'))->toBeTrue();
});

it('verifies if the permission_role table columns exist', function () {
    $columns = [
        'permission_id',
        'role_id',
    ];

    expect(Schema::hasColumns('permission_role', $columns))->toBeTrue();
});

it('verifies the permission_role table column types', function () {
    expect(Schema::getColumnType('permission_role', 'permission_id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('permission_role', 'role_id'))->toBeIn(['uuid', 'varchar', 'string']);
});

it('verifies the permission_role table column indexes', function () {
    $foreignKeys = collect(Schema::getForeignKeys('permission_role'));

    $permissionForeignKey = $foreignKeys->where('columns', ['permission_id'])->first();
    expect($permissionForeignKey)->not->toBeNull()
        ->and($permissionForeignKey['foreign_table'])->toBe('permissions')
        ->and($permissionForeignKey['foreign_columns'])->toBe(['id']);

    $roleForeignKey = $foreignKeys->where('columns', ['role_id'])->first();
    expect($roleForeignKey)->not->toBeNull()
        ->and($roleForeignKey['foreign_table'])->toBe('roles')
        ->and($roleForeignKey['foreign_columns'])->toBe(['id']);
});
