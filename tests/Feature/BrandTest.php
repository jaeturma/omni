<?php

use App\Models\Brand;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function brandData(array $changes = []): array
{
    return array_merge(['code' => 'acer', 'name' => '  acer  ', 'status' => 'active'], $changes);
}

test('authorized users can list create and update brands', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('brands.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('brands.store'), brandData())->assertRedirect(route('brands.index'));
    $brand = Brand::query()->sole();
    expect($brand->code)->toBe('ACER')->and($brand->name)->toBe('Acer');
    $this->actingAs($admin)->put(route('brands.update', $brand), brandData(['code' => 'dell', 'name' => 'dell', 'status' => 'inactive']))->assertRedirect(route('brands.index'));
    expect($brand->fresh()->status)->toBe('inactive');
});

test('brand validation and duplicate prevention are enforced', function () {
    $admin = User::factory()->administrator()->create();
    Brand::factory()->create(['code' => 'ACER', 'name' => 'Acer']);
    $this->actingAs($admin)->post(route('brands.store'), brandData())->assertSessionHasErrors(['code', 'name']);
    $this->actingAs($admin)->post(route('brands.store'), brandData(['code' => 'bad code!', 'name' => '', 'status' => 'deleted']))->assertSessionHasErrors(['code', 'name', 'status']);
});

test('brand listing supports search and status filters', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Brand::factory()->count(26)->create();
    Brand::factory()->create(['code' => 'SPECIAL', 'name' => 'Special Brand']);
    $this->actingAs($viewer)->get(route('brands.index', ['search' => 'SPECIAL', 'status' => 'active']))->assertSuccessful()->assertSee('Special Brand');
});

test('brand authorization and deletion are enforced', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $admin = User::factory()->administrator()->create();
    $brand = Brand::factory()->create();
    $this->actingAs($viewer)->get(route('brands.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('brands.store'), brandData())->assertForbidden();
    $this->actingAs($encoder)->delete(route('brands.destroy', $brand))->assertForbidden();
    $this->actingAs($admin)->delete(route('brands.destroy', $brand))->assertRedirect(route('brands.index'));
    $this->assertModelMissing($brand);
});

test('guests cannot access brands', function () {
    $this->get(route('brands.index'))->assertRedirect(route('login'));
});
