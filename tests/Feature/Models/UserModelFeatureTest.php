<?php

use App\Models\EmailVerification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a user successfully with valid data and generates a valid uuid', function () {
    // Arrange & Act
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
        'last_login_at' => now(),
    ]);

    // Assert
    expect($user)->toBeInstanceOf(User::class)
        ->and(Str::isUuid($user->id))->toBeTrue();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_active' => true,
    ]);
});

it('enforces strict typing for the is_active boolean field', function () {
    // Arrange
    // In PostgreSQL, sending a string that cannot be cast to boolean throws a database exception upon saving
    $user = User::factory()->make(['is_active' => 'invalid_string']);

    // Act & Assert
    expect(fn () => $user->save())->toThrow(Exception::class);
});

it('updates a user successfully', function () {
    // Arrange
    $user = User::factory()->create(['name' => 'Old Name']);

    // Act
    $user->update(['name' => 'New Name']);

    // Assert
    expect($user->fresh()->name)->toBe('New Name');
});

it('soft deletes a user successfully', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->delete();

    // Assert
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

it('has many-to-many relationship with roles', function () {
    // Arrange
    $user = User::factory()->create();
    $role = Role::factory()->create();

    // Act
    $user->roles()->attach($role->id);

    // Assert
    expect($user->roles)->toHaveCount(1)
        ->and($user->roles->first()->id)->toBe($role->id);
});

it('has one-to-many relationship with email verifications', function () {
    // Arrange
    $user = User::factory()->create();
    EmailVerification::factory()->create(['user_id' => $user->id]);

    // Assert
    expect($user->emailVerifications)->toHaveCount(1)
        ->and($user->emailVerifications->first()->user_id)->toBe($user->id);
});
