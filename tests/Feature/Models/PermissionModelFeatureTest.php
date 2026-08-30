<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a permission successfully with valid data and generates a valid uuid', function () {
    // Arrange & Act
    $permission = Permission::factory()->create([
        'name' => 'edit-posts',
        'label' => 'Edit Posts Permission',
    ]);

    // Assert
    expect($permission)->toBeInstanceOf(Permission::class)
        ->and(Str::isUuid($permission->id))->toBeTrue();

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => 'edit-posts',
        'label' => 'Edit Posts Permission',
    ]);
});

it('enforces unique constraint for the name field', function () {
    // Arrange
    Permission::factory()->create(['name' => 'unique-permission']);
    $duplicatePermission = Permission::factory()->make(['name' => 'unique-permission']);

    // Act & Assert
    expect(fn () => $duplicatePermission->save())->toThrow(Exception::class);
});

it('updates a permission successfully', function () {
    // Arrange
    $permission = Permission::factory()->create(['label' => 'Old Label']);

    // Act
    $permission->update(['label' => 'New Label']);

    // Assert
    expect($permission->fresh()->label)->toBe('New Label');
});

it('soft deletes a permission successfully', function () {
    // Arrange
    $permission = Permission::factory()->create();

    // Act
    $permission->delete();

    // Assert
    $this->assertSoftDeleted('permissions', ['id' => $permission->id]);
});

it('has many-to-many relationship with roles', function () {
    // Arrange
    $permission = Permission::factory()->create();
    $role = Role::factory()->create();

    // Act
    $permission->roles()->attach($role->id);

    // Assert
    expect($permission->roles)->toHaveCount(1)
        ->and($permission->roles->first()->id)->toBe($role->id);
});
