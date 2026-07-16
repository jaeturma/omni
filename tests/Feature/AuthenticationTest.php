<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a guest can view the login page', function () {
    $this->get(route('login'))->assertSuccessful();
});

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('an invalid login is rejected', function () {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login credentials are required', function () {
    $this->from(route('login'))
        ->post(route('login.store'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email', 'password']);
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('guests are redirected from the dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
