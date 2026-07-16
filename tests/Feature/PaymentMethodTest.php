<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));
function paymentMethodData(array $changes = []): array
{
    return array_merge(['code' => 'gcash', 'name' => '  gcash  ', 'type' => 'gcash', 'status' => 'active'], $changes);
}
test('authorized users can list create and update controlled payment methods', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('payment-methods.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('payment-methods.store'), paymentMethodData())->assertRedirect(route('payment-methods.index'));
    $method = PaymentMethod::query()->sole();
    expect($method->code)->toBe('GCASH')->and($method->type)->toBe('gcash');
    $this->actingAs($admin)->put(route('payment-methods.update', $method), paymentMethodData(['code' => 'check', 'name' => 'Cheque', 'type' => 'cheque', 'status' => 'inactive']))->assertRedirect(route('payment-methods.index'));
    expect($method->fresh()->status)->toBe('inactive');
});
test('payment method validation duplicates and controlled types are enforced', function () {
    $admin = User::factory()->administrator()->create();
    PaymentMethod::factory()->create(['code' => 'GCASH', 'name' => 'Gcash']);
    $this->actingAs($admin)->post(route('payment-methods.store'), paymentMethodData())->assertSessionHasErrors(['code', 'name']);
    $this->actingAs($admin)->post(route('payment-methods.store'), paymentMethodData(['code' => 'bad code!', 'name' => '', 'type' => 'crypto', 'status' => 'deleted']))->assertSessionHasErrors(['code', 'name', 'type', 'status']);
});
test('payment method listing supports type and status filters', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    PaymentMethod::factory()->count(26)->create();
    PaymentMethod::factory()->create(['code' => 'SPECIAL', 'name' => 'Special Transfer', 'type' => 'online_transfer']);
    $this->actingAs($viewer)->get(route('payment-methods.index', ['search' => 'SPECIAL', 'type' => 'online_transfer', 'status' => 'active']))->assertSuccessful()->assertSee('Special Transfer');
});
test('payment method authorization and deletion are enforced', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $admin = User::factory()->administrator()->create();
    $method = PaymentMethod::factory()->create();
    $this->actingAs($viewer)->get(route('payment-methods.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('payment-methods.store'), paymentMethodData())->assertForbidden();
    $this->actingAs($encoder)->delete(route('payment-methods.destroy', $method))->assertForbidden();
    $this->actingAs($admin)->delete(route('payment-methods.destroy', $method))->assertRedirect(route('payment-methods.index'));
    $this->assertModelMissing($method);
});
test('guests cannot access payment methods', function () {
    $this->get(route('payment-methods.index'))->assertRedirect(route('login'));
});
