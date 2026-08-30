<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies if the permissions table exists', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue();
});

it('verifies if the permissions table columns exist', function () {
    $columns = [
        'id',
        'name',
        'label',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    expect(Schema::hasColumns('permissions', $columns))->toBeTrue();
});

it('verifies the permissions table column types', function () {
    expect(Schema::getColumnType('permissions', 'id'))->toBeIn(['uuid', 'varchar', 'string'])
        ->and(Schema::getColumnType('permissions', 'name'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('permissions', 'label'))->toBeIn(['varchar', 'string'])
        ->and(Schema::getColumnType('permissions', 'created_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('permissions', 'updated_at'))->toBeIn(['timestamp', 'datetime'])
        ->and(Schema::getColumnType('permissions', 'deleted_at'))->toBeIn(['timestamp', 'datetime']);
});

it('verifies the permissions table column indexes', function () {
    $indexes = collect(Schema::getIndexes('permissions'));

    $primaryKey = $indexes->where('primary', true)->first();
    expect($primaryKey)->not->toBeNull()
        ->and($primaryKey['columns'])->toBe(['id']);

    $uniqueName = $indexes->where('unique', true)->where('columns', ['name'])->first();
    expect($uniqueName)->not->toBeNull();
});
