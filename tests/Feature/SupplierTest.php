<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function supplierData(array $changes = []): array
{
    return array_merge([
        'code' => 'sup-0001', 'name' => 'Sample ICT Distributor', 'tin' => '123-456-789-00000',
        'address' => 'Commerce Avenue, Sample City', 'contact_person' => 'Maria Santos',
        'email' => 'sales@supplier.example', 'phone' => '09171234567', 'payment_terms' => 30, 'status' => 'active',
    ], $changes);
}

test('authorized users can list create and update suppliers', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('suppliers.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('suppliers.store'), supplierData())->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::query()->sole();
    expect($supplier->code)->toBe('SUP-0001')->and($supplier->payment_terms)->toBe(30);

    $this->actingAs($admin)->put(route('suppliers.update', $supplier), supplierData(['name' => 'Updated Distributor', 'tin' => '987-654-321-00000', 'status' => 'inactive']))->assertRedirect(route('suppliers.index'));
    expect($supplier->fresh()->name)->toBe('Updated Distributor')->and($supplier->fresh()->status)->toBe('inactive');
});

test('supplier listing supports search and status filtering with pagination', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Supplier::factory()->count(26)->create();
    Supplier::factory()->create(['code' => 'SUP-SPECIAL', 'name' => 'Special Distributor', 'status' => 'active']);

    $this->actingAs($viewer)->get(route('suppliers.index', ['search' => 'SPECIAL', 'status' => 'active']))
        ->assertSuccessful()->assertSee('Special Distributor')->assertDontSee(Supplier::query()->where('code', '!=', 'SUP-SPECIAL')->firstOrFail()->name);
});

test('supplier validation enforces required fields formats and ranges', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('suppliers.store'), supplierData([
        'code' => 'invalid code!', 'name' => '', 'tin' => 'invalid', 'address' => '',
        'email' => 'invalid', 'payment_terms' => -1, 'status' => 'deleted',
    ]))->assertSessionHasErrors(['code', 'name', 'tin', 'address', 'email', 'payment_terms', 'status']);
});

test('duplicate supplier codes and TINs are prevented', function () {
    $admin = User::factory()->administrator()->create();
    Supplier::factory()->create(['code' => 'SUP-0001', 'tin' => '123-456-789-00000']);

    $this->actingAs($admin)->post(route('suppliers.store'), supplierData())->assertSessionHasErrors(['code', 'tin']);
    expect(Supplier::query()->count())->toBe(1);
});

test('supplier permissions distinguish viewing editing and deletion', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $supplier = Supplier::factory()->create();

    $this->actingAs($viewer)->get(route('suppliers.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('suppliers.store'), supplierData())->assertForbidden();
    $this->actingAs($encoder)->get(route('suppliers.edit', $supplier))->assertSuccessful();
    $this->actingAs($encoder)->delete(route('suppliers.destroy', $supplier))->assertForbidden();
});

test('administrators can delete supplier masters before transactional use', function () {
    $admin = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
    $this->assertModelMissing($supplier);
});

test('guests cannot access suppliers', function () {
    $this->get(route('suppliers.index'))->assertRedirect(route('login'));
});
