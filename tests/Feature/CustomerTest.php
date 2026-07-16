<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function customerData(array $changes = []): array
{
    return array_merge([
        'code' => 'cus-0001', 'name' => 'DepEd Sample Division', 'type' => 'government',
        'tin' => '123-456-789-00000', 'address' => 'Government Center, Sample City',
        'contact_person' => 'Juan Dela Cruz', 'email' => 'procurement@example.gov.ph', 'phone' => '09171234567',
        'payment_terms' => 30, 'status' => 'active',
    ], $changes);
}

test('authorized users can list create and update private and government customers', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('customers.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('customers.store'), customerData())->assertRedirect(route('customers.index'));

    $customer = Customer::query()->sole();
    expect($customer->code)->toBe('CUS-0001')->and($customer->type)->toBe('government')->and($customer->payment_terms)->toBe(30);

    $this->actingAs($admin)->put(route('customers.update', $customer), customerData(['name' => 'Updated Agency', 'type' => 'private', 'tin' => '987-654-321-00000', 'status' => 'inactive']))->assertRedirect(route('customers.index'));
    expect($customer->fresh()->name)->toBe('Updated Agency')->and($customer->fresh()->status)->toBe('inactive');
});

test('customer listing supports search and filters with pagination', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Customer::factory()->count(26)->create();
    Customer::factory()->create(['code' => 'GOV-DEPED', 'name' => 'DepEd Division', 'type' => 'government', 'status' => 'active']);

    $this->actingAs($viewer)->get(route('customers.index', ['search' => 'DEPED', 'type' => 'government', 'status' => 'active']))
        ->assertSuccessful()->assertSee('DepEd Division')->assertDontSee(Customer::query()->where('code', '!=', 'GOV-DEPED')->firstOrFail()->name);
});

test('customer validation enforces required fields formats types and ranges', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('customers.store'), customerData([
        'code' => 'invalid code!', 'name' => '', 'type' => 'supplier', 'tin' => 'invalid',
        'address' => '', 'email' => 'invalid', 'payment_terms' => -1, 'status' => 'deleted',
    ]))->assertSessionHasErrors(['code', 'name', 'type', 'tin', 'address', 'email', 'payment_terms', 'status']);
});

test('duplicate customer codes and TINs are prevented', function () {
    $admin = User::factory()->administrator()->create();
    Customer::factory()->create(['code' => 'CUS-0001', 'tin' => '123-456-789-00000']);

    $this->actingAs($admin)->post(route('customers.store'), customerData())->assertSessionHasErrors(['code', 'tin']);
    expect(Customer::query()->count())->toBe(1);
});

test('customer permissions distinguish viewing editing and deletion', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $customer = Customer::factory()->create();

    $this->actingAs($viewer)->get(route('customers.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('customers.store'), customerData())->assertForbidden();
    $this->actingAs($encoder)->get(route('customers.edit', $customer))->assertSuccessful();
    $this->actingAs($encoder)->delete(route('customers.destroy', $customer))->assertForbidden();
});

test('administrators can delete customer masters before transactional use', function () {
    $admin = User::factory()->administrator()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));
    $this->assertModelMissing($customer);
});

test('guests cannot access customers', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});
