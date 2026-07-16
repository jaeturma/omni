<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('authenticated users see the application shell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Omni Mini-ERP')
        ->assertSee('Dashboard')
        ->assertSee($user->name)
        ->assertSee('Sign out');
});

test('the shell shows placeholders only for modules not yet implemented', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee('Purchases')
        ->assertSee('Inventory')
        ->assertSee('Accounting')
        ->assertSee('Tax Reports');
});

test('the shell renders success flash messages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Changes saved successfully.'])
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Changes saved successfully.');
});
