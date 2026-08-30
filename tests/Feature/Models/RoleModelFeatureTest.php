<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a role successfully with valid data and generates a valid uuid', function () {
    // Arrange & Act
    $role = Role::factory()->create([
        'name' => 'admin',
        'label' => 'Administrator',
    ]);

    // Assert
    expect($role)->toBeInstanceOf(Role::class)
        ->and(Str::isUuid($role->id))->toBeTrue();

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'admin',
        'label' => 'Administrator',
    ]);
});

it('enforces unique constraint for the name field', function () {
    // Arrange
    Role::factory()->create(['name' => 'unique-role']);
    $duplicateRole = Role::factory()->make(['name' => 'unique-role']);

    // Act & Assert
    expect(fn () => $duplicateRole->save())->toThrow(Exception::class);
});

it('updates a role successfully', function () {
    // Arrange
    $role = Role::factory()->create(['label' => 'Old Label']);

    // Act
    $role->update(['label' => 'New Label']);

    // Assert
    expect($role->fresh()->label)->toBe('New Label');
});

it('soft deletes a role successfully', function () {
    // Arrange
    $role = Role::factory()->create();

    // Act
    $role->delete();

    // Assert
    $this->assertSoftDeleted('roles', ['id' => $role->id]);
});

it('has self-referencing parent and children relationships', function () {
    // Arrange
    $parentRole = Role::factory()->create(['name' => 'parent']);
    $childRole = Role::factory()->create([
        'name' => 'child',
        'parent_id' => $parentRole->id,
    ]);

    // Assert parent relationship
    expect($childRole->parent)->not->toBeNull()
        ->and($childRole->parent->id)->toBe($parentRole->id);

    // Assert children relationship
    expect($parentRole->children)->toHaveCount(1)
        ->and($parentRole->children->first()->id)->toBe($childRole->id);
});

it('has many-to-many relationship with users', function () {
    // Arrange
    $role = Role::factory()->create();
    $user = User::factory()->create();

    // Act
    $role->users()->attach($user->id);

    // Assert
    expect($role->users)->toHaveCount(1)
        ->and($role->users->first()->id)->toBe($user->id);
});

it('has many-to-many relationship with permissions', function () {
    // Arrange
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    // Act
    $role->permissions()->attach($permission->id);

    // Assert
    expect($role->permissions)->toHaveCount(1)
        ->and($role->permissions->first()->id)->toBe($permission->id);
});
