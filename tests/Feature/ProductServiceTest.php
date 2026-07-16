<?php

use App\Models\Category;
use App\Models\ProductService;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function productServiceData(Category $category, UnitOfMeasure $unit, array $changes = []): array
{
    return array_merge([
        'sku' => 'item-0001', 'barcode' => '4801234567890', 'name' => 'Business Laptop',
        'description' => 'Standard office laptop', 'type' => 'product', 'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id, 'default_cost' => '25000.1250', 'selling_price' => '30000.5000',
        'reorder_level' => '3.5000', 'is_inventory' => '1', 'status' => 'active',
    ], $changes);
}

test('authorized users can create and update product and service catalog records', function () {
    $admin = User::factory()->administrator()->create();
    $productCategory = Category::factory()->create(['type' => 'product']);
    $serviceCategory = Category::factory()->create(['type' => 'service']);
    $unit = UnitOfMeasure::factory()->create();
    $this->actingAs($admin)->get(route('products-services.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($productCategory, $unit))->assertRedirect(route('products-services.index'));

    $item = ProductService::query()->sole();
    expect($item->sku)->toBe('ITEM-0001')->and($item->default_cost)->toBe('25000.1250')->and($item->is_inventory)->toBeTrue();

    $this->actingAs($admin)->put(route('products-services.update', $item), productServiceData($serviceCategory, $unit, [
        'sku' => 'svc-0001', 'barcode' => null, 'name' => 'Installation Service', 'type' => 'service',
        'reorder_level' => '0.0000', 'is_inventory' => '0', 'status' => 'inactive',
    ]))->assertRedirect(route('products-services.index'));
    expect($item->fresh()->type)->toBe('service')->and($item->fresh()->is_inventory)->toBeFalse();
});

test('catalog listing supports search type and status filters with pagination', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    ProductService::factory()->count(26)->create();
    ProductService::factory()->create(['sku' => 'SVC-SPECIAL', 'name' => 'Special Service', 'type' => 'service', 'is_inventory' => false, 'reorder_level' => 0, 'status' => 'active', 'category_id' => Category::factory()->state(['type' => 'service'])]);

    $this->actingAs($viewer)->get(route('products-services.index', ['search' => 'SPECIAL', 'type' => 'service', 'status' => 'active']))
        ->assertSuccessful()->assertSee('Special Service')->assertDontSee(ProductService::query()->where('sku', '!=', 'SVC-SPECIAL')->firstOrFail()->name);
});

test('catalog validation enforces required fields formats references and decimal ranges', function () {
    $admin = User::factory()->administrator()->create();
    $category = Category::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($category, $unit, [
        'sku' => 'invalid sku!', 'name' => '', 'type' => 'stock', 'category_id' => 999999,
        'unit_of_measure_id' => 999999, 'default_cost' => '-1', 'selling_price' => '1.12345',
        'reorder_level' => '-1', 'is_inventory' => 'invalid', 'status' => 'deleted',
    ]))->assertSessionHasErrors(['sku', 'name', 'type', 'category_id', 'unit_of_measure_id', 'default_cost', 'selling_price', 'reorder_level', 'is_inventory', 'status']);
});

test('duplicate SKUs and barcodes are prevented', function () {
    $admin = User::factory()->administrator()->create();
    $category = Category::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    ProductService::factory()->create(['sku' => 'ITEM-0001', 'barcode' => '4801234567890']);

    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($category, $unit))->assertSessionHasErrors(['sku', 'barcode']);
});

test('catalog category type and inventory business rules are enforced', function () {
    $admin = User::factory()->administrator()->create();
    $productCategory = Category::factory()->create(['type' => 'product']);
    $serviceCategory = Category::factory()->create(['type' => 'service']);
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($serviceCategory, $unit))->assertSessionHasErrors('category_id');
    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($serviceCategory, $unit, ['type' => 'service']))->assertSessionHasErrors('is_inventory');
    $this->actingAs($admin)->post(route('products-services.store'), productServiceData($productCategory, $unit, ['is_inventory' => '0']))->assertSessionHasErrors('reorder_level');
});

test('catalog permissions distinguish viewing editing and deletion', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $item = ProductService::factory()->create();
    $category = Category::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($viewer)->get(route('products-services.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('products-services.store'), productServiceData($category, $unit))->assertForbidden();
    $this->actingAs($encoder)->get(route('products-services.edit', $item))->assertSuccessful();
    $this->actingAs($encoder)->delete(route('products-services.destroy', $item))->assertForbidden();
});

test('administrators can delete unused catalog records', function () {
    $admin = User::factory()->administrator()->create();
    $item = ProductService::factory()->create();

    $this->actingAs($admin)->delete(route('products-services.destroy', $item))->assertRedirect(route('products-services.index'));
    $this->assertModelMissing($item);
});

test('guests cannot access the product and service catalog', function () {
    $this->get(route('products-services.index'))->assertRedirect(route('login'));
});
