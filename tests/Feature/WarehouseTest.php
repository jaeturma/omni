<?php

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function warehouseData(array $changes = []): array
{
    return array_merge(['code' => 'main', 'name' => '  main warehouse  ', 'address' => 'Business Center, Sample City', 'status' => 'active'], $changes);
}

test('authorized users can list create and update warehouse locations', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('warehouses.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('warehouses.store'), warehouseData())->assertRedirect(route('warehouses.index'));
    $warehouse = Warehouse::query()->sole();
    expect($warehouse->code)->toBe('MAIN')->and($warehouse->name)->toBe('Main Warehouse');
    $this->actingAs($admin)->put(route('warehouses.update', $warehouse), warehouseData(['address' => 'Updated Location', 'status' => 'inactive']))->assertRedirect(route('warehouses.index'));
    expect($warehouse->fresh()->address)->toBe('Updated Location')->and($warehouse->fresh()->status)->toBe('inactive');
});

test('warehouse validation and duplicate prevention are enforced', function () {
    $admin = User::factory()->administrator()->create();
    Warehouse::factory()->create(['code' => 'MAIN', 'name' => 'Main Warehouse']);
    $this->actingAs($admin)->post(route('warehouses.store'), warehouseData())->assertSessionHasErrors(['code', 'name']);
    $this->actingAs($admin)->post(route('warehouses.store'), warehouseData(['code' => 'bad code!', 'name' => '', 'address' => '', 'status' => 'deleted']))->assertSessionHasErrors(['code', 'name', 'address', 'status']);
});

test('warehouse listing supports search and status filters', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Warehouse::factory()->count(26)->create();
    Warehouse::factory()->create(['code' => 'SPECIAL', 'name' => 'Special Warehouse']);
    $this->actingAs($viewer)->get(route('warehouses.index', ['search' => 'SPECIAL', 'status' => 'active']))->assertSuccessful()->assertSee('Special Warehouse');
});

test('warehouse authorization and deletion are enforced', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $admin = User::factory()->administrator()->create();
    $warehouse = Warehouse::factory()->create();
    $this->actingAs($viewer)->get(route('warehouses.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('warehouses.store'), warehouseData())->assertForbidden();
    $this->actingAs($encoder)->delete(route('warehouses.destroy', $warehouse))->assertForbidden();
    $this->actingAs($admin)->delete(route('warehouses.destroy', $warehouse))->assertRedirect(route('warehouses.index'));
    $this->assertModelMissing($warehouse);
});

test('guests cannot access warehouses', function () {
    $this->get(route('warehouses.index'))->assertRedirect(route('login'));
});
