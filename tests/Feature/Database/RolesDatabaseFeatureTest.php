<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the roles table exists', function () {
    expect(Schema::hasTable('roles'))->toBeTrue();
});

it('verifies if the roles table columns exist', function () {
    $columns = [
        'id',
        'parent_id',
        'name',
        'label',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    expect(Schema::hasColumns('roles', $columns))->toBeTrue();
});

it('verifies the roles table column types', function () {
    expect(Schema::getColumnType('roles', 'id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('roles', 'parent_id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('roles', 'name'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('roles', 'label'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('roles', 'created_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('roles', 'updated_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('roles', 'deleted_at'))->toBeIn(['timestamp', 'datetime']);
});

it('verifies the roles table column indexes', function () {
    $indexes = collect(Schema::getIndexes('roles'));

    $primaryKey = $indexes->where('primary', true)->first();
    expect($primaryKey)->not->toBeNull()
        ->and($primaryKey['columns'])->toBe(['id']);

    $uniqueName = $indexes->where('unique', true)->where('columns', ['name'])->first();
    expect($uniqueName)->not->toBeNull();

    $foreignKeys = collect(Schema::getForeignKeys('roles'));
    $parentForeignKey = $foreignKeys->where('columns', ['parent_id'])->first();

    expect($parentForeignKey)->not->toBeNull()
        ->and($parentForeignKey['foreign_table'])->toBe('roles')
        ->and($parentForeignKey['foreign_columns'])->toBe(['id']);
});
