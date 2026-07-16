<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function categoryData(array $changes = []): array
{
    return array_merge(['code' => 'ict', 'name' => '  ict equipment  ', 'type' => 'product', 'parent_id' => null, 'status' => 'active'], $changes);
}

test('authorized users can create and update product and service category hierarchies', function () {
    $admin = User::factory()->administrator()->create();
    $parent = Category::factory()->create(['type' => 'product']);
    $this->actingAs($admin)->get(route('categories.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('categories.store'), categoryData(['parent_id' => $parent->id]))->assertRedirect(route('categories.index'));

    $category = Category::query()->where('code', 'ICT')->sole();
    expect($category->name)->toBe('Ict Equipment')->and($category->parent_id)->toBe($parent->id);

    $this->actingAs($admin)->put(route('categories.update', $category), categoryData(['code' => 'repair', 'name' => 'repair services', 'type' => 'service', 'parent_id' => null, 'status' => 'inactive']))->assertRedirect(route('categories.index'));
    expect($category->fresh()->type)->toBe('service')->and($category->fresh()->status)->toBe('inactive');
});

test('category listing supports search type and status filters with pagination', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Category::factory()->count(26)->create();
    Category::factory()->create(['code' => 'SVC-SPECIAL', 'name' => 'Special Service', 'type' => 'service', 'status' => 'active']);

    $this->actingAs($viewer)->get(route('categories.index', ['search' => 'SPECIAL', 'type' => 'service', 'status' => 'active']))
        ->assertSuccessful()->assertSee('Special Service')->assertDontSee(Category::query()->where('code', '!=', 'SVC-SPECIAL')->firstOrFail()->name);
});

test('category validation enforces required fields type format and status', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('categories.store'), categoryData([
        'code' => 'invalid code!', 'name' => '', 'type' => 'inventory', 'parent_id' => 999999, 'status' => 'deleted',
    ]))->assertSessionHasErrors(['code', 'name', 'type', 'parent_id', 'status']);
});

test('duplicate category codes and normalized names are prevented within a type', function () {
    $admin = User::factory()->administrator()->create();
    Category::factory()->create(['code' => 'ICT', 'name' => 'Ict Equipment', 'type' => 'product']);

    $this->actingAs($admin)->post(route('categories.store'), categoryData())->assertSessionHasErrors(['code', 'name']);
    $this->actingAs($admin)->post(route('categories.store'), categoryData(['type' => 'service']))->assertRedirect(route('categories.index'));
});

test('category hierarchy requires matching types and prevents cycles', function () {
    $admin = User::factory()->administrator()->create();
    $product = Category::factory()->create(['type' => 'product']);
    $service = Category::factory()->create(['type' => 'service']);
    $child = Category::factory()->create(['type' => 'product', 'parent_id' => $product->id]);

    $this->actingAs($admin)->post(route('categories.store'), categoryData(['parent_id' => $service->id]))->assertSessionHasErrors('parent_id');
    $this->actingAs($admin)->put(route('categories.update', $product), categoryData(['code' => $product->code, 'name' => $product->name, 'parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
});

test('category permissions distinguish viewing editing and deletion', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $category = Category::factory()->create();

    $this->actingAs($viewer)->get(route('categories.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('categories.store'), categoryData())->assertForbidden();
    $this->actingAs($encoder)->get(route('categories.edit', $category))->assertSuccessful();
    $this->actingAs($encoder)->delete(route('categories.destroy', $category))->assertForbidden();
});

test('administrators can delete unused categories but not parents with children', function () {
    $admin = User::factory()->administrator()->create();
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($admin)->delete(route('categories.destroy', $parent))->assertRedirect()->assertSessionHas('error');
    $this->assertModelExists($parent);
    $this->actingAs($admin)->delete(route('categories.destroy', $child))->assertRedirect(route('categories.index'));
    $this->assertModelMissing($child);
});

test('guests cannot access categories', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});
