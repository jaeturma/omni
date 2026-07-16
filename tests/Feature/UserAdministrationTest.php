<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function managedUserData(array $changes = []): array
{
    return array_merge(['name' => 'Managed User', 'email' => 'managed@example.com', 'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!', 'active' => true, 'roles' => ['Bookkeeper']], $changes);
}

test('initial roles and grouped phase one permissions exist', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['Administrator', 'Bookkeeper', 'Encoder', 'Owner', 'Viewer'])
        ->and(Permission::query()->where('name', 'users.manage')->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', ['business-profile.update', 'tax-profile.update', 'tax-rates.manage'])->count())->toBe(3)
        ->and(Permission::query()->whereIn('name', ['business-profile.manage', 'tax-profile.manage'])->exists())->toBeFalse()
        ->and(Role::findByName('Administrator')->permissions)->toHaveCount(Permission::query()->count());
});

test('administrators can list create update and assign roles with hashed passwords', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('users.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('users.store'), managedUserData())->assertRedirect(route('users.index'));

    $managed = User::query()->where('email', 'managed@example.com')->sole();
    expect($managed->hasRole('Bookkeeper'))->toBeTrue()->and(Hash::check('SecurePassword123!', $managed->password))->toBeTrue();

    $this->actingAs($admin)->put(route('users.update', $managed), managedUserData(['name' => 'Updated User', 'password' => '', 'password_confirmation' => '', 'roles' => ['Viewer'], 'active' => false]))->assertSessionHasNoErrors();
    expect($managed->fresh()->name)->toBe('Updated User')->and($managed->fresh()->active)->toBeFalse()->and($managed->fresh()->hasRole('Viewer'))->toBeTrue();
});

test('users without management permission are denied administration', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('users.store'), managedUserData())->assertForbidden();
});

test('phase one permissions protect viewing and management independently', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('fiscal-years.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('fiscal-years.store'), ['name' => 'FY 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'is_current' => true])->assertForbidden();
    $this->actingAs($viewer)->get(route('roles.index'))->assertSuccessful()->assertSee('Role-Permission Matrix');
});

test('inactive users cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('deactivated users cannot continue using an existing session', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('the last active administrator cannot be deactivated or demoted', function () {
    $admin = User::factory()->administrator()->create();
    $owner = User::factory()->create();
    $owner->assignRole('Owner');

    $this->actingAs($owner)->put(route('users.update', $admin), managedUserData(['name' => $admin->name, 'email' => $admin->email, 'password' => '', 'password_confirmation' => '', 'active' => false, 'roles' => ['Viewer']]))->assertSessionHasErrors('roles');
    expect($admin->fresh()->active)->toBeTrue()->and($admin->fresh()->hasRole('Administrator'))->toBeTrue();
});

test('administrators cannot lock themselves out', function () {
    $admin = User::factory()->administrator()->create();
    User::factory()->administrator()->create();

    $this->actingAs($admin)->put(route('users.update', $admin), managedUserData(['name' => $admin->name, 'email' => $admin->email, 'password' => '', 'password_confirmation' => '', 'active' => false, 'roles' => ['Viewer']]))->assertSessionHasErrors('roles');
    expect($admin->fresh()->active)->toBeTrue()->and($admin->fresh()->hasRole('Administrator'))->toBeTrue();
});

test('owners cannot remove their own user management access', function () {
    $owner = User::factory()->create();
    $owner->assignRole('Owner');

    $this->actingAs($owner)->put(route('users.update', $owner), managedUserData(['name' => $owner->name, 'email' => $owner->email, 'password' => '', 'password_confirmation' => '', 'roles' => ['Viewer']]))->assertSessionHasErrors('roles');
    expect($owner->fresh()->hasRole('Owner'))->toBeTrue();
});
