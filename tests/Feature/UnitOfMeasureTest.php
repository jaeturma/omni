<?php

use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function unitOfMeasureData(array $changes = []): array
{
    return array_merge(['code' => 'pcs', 'name' => '  pieces  ', 'status' => 'active'], $changes);
}

test('authorized users can list create and update units of measure', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('units-of-measure.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('units-of-measure.store'), unitOfMeasureData())->assertRedirect(route('units-of-measure.index'));

    $unit = UnitOfMeasure::query()->sole();
    expect($unit->code)->toBe('PCS')->and($unit->name)->toBe('Pieces');

    $this->actingAs($admin)->put(route('units-of-measure.update', $unit), unitOfMeasureData(['code' => 'box', 'name' => 'box', 'status' => 'inactive']))->assertRedirect(route('units-of-measure.index'));
    expect($unit->fresh()->code)->toBe('BOX')->and($unit->fresh()->status)->toBe('inactive');
});

test('unit listing supports search and status filtering with pagination', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    UnitOfMeasure::factory()->count(26)->create();
    UnitOfMeasure::factory()->create(['code' => 'REAM', 'name' => 'Paper Ream', 'status' => 'active']);

    $this->actingAs($viewer)->get(route('units-of-measure.index', ['search' => 'REAM', 'status' => 'active']))
        ->assertSuccessful()->assertSee('Paper Ream')->assertDontSee(UnitOfMeasure::query()->where('code', '!=', 'REAM')->firstOrFail()->name);
});

test('unit validation enforces required fields formats and status', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('units-of-measure.store'), unitOfMeasureData([
        'code' => 'invalid code!', 'name' => '', 'status' => 'deleted',
    ]))->assertSessionHasErrors(['code', 'name', 'status']);
});

test('duplicate unit codes and normalized names are prevented', function () {
    $admin = User::factory()->administrator()->create();
    UnitOfMeasure::factory()->create(['code' => 'PCS', 'name' => 'Pieces']);

    $this->actingAs($admin)->post(route('units-of-measure.store'), unitOfMeasureData())->assertSessionHasErrors(['code', 'name']);
    expect(UnitOfMeasure::query()->count())->toBe(1);
});

test('unit permissions distinguish viewing editing and deletion', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($viewer)->get(route('units-of-measure.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('units-of-measure.store'), unitOfMeasureData())->assertForbidden();
    $this->actingAs($encoder)->get(route('units-of-measure.edit', $unit))->assertSuccessful();
    $this->actingAs($encoder)->delete(route('units-of-measure.destroy', $unit))->assertForbidden();
});

test('administrators can delete unused unit masters', function () {
    $admin = User::factory()->administrator()->create();
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($admin)->delete(route('units-of-measure.destroy', $unit))->assertRedirect(route('units-of-measure.index'));
    $this->assertModelMissing($unit);
});

test('guests cannot access units of measure', function () {
    $this->get(route('units-of-measure.index'))->assertRedirect(route('login'));
});
