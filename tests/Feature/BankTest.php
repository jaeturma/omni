<?php

use App\Models\Bank;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));
function bankData(array $changes = []): array
{
    return array_merge(['code' => 'bpi', 'name' => '  bank of the philippine islands  ', 'swift_code' => 'bopiphmm', 'status' => 'active'], $changes);
}
test('authorized users can list create and update banks', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('banks.index'))->assertSuccessful();
    $this->actingAs($admin)->post(route('banks.store'), bankData())->assertRedirect(route('banks.index'));
    $bank = Bank::query()->sole();
    expect($bank->code)->toBe('BPI')->and($bank->swift_code)->toBe('BOPIPHMM');
    $this->actingAs($admin)->put(route('banks.update', $bank), bankData(['swift_code' => null, 'status' => 'inactive']))->assertRedirect(route('banks.index'));
    expect($bank->fresh()->status)->toBe('inactive');
});
test('bank validation and duplicate prevention are enforced', function () {
    $admin = User::factory()->administrator()->create();
    Bank::factory()->create(['code' => 'BPI', 'name' => 'Bank Of The Philippine Islands', 'swift_code' => 'BOPIPHMM']);
    $this->actingAs($admin)->post(route('banks.store'), bankData())->assertSessionHasErrors(['code', 'name', 'swift_code']);
    $this->actingAs($admin)->post(route('banks.store'), bankData(['code' => 'bad code!', 'name' => '', 'swift_code' => 'bad', 'status' => 'deleted']))->assertSessionHasErrors(['code', 'name', 'swift_code', 'status']);
});
test('bank listing supports search and status filters', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    Bank::factory()->count(26)->create();
    Bank::factory()->create(['code' => 'SPECIAL', 'name' => 'Special Bank']);
    $this->actingAs($viewer)->get(route('banks.index', ['search' => 'SPECIAL', 'status' => 'active']))->assertSuccessful()->assertSee('Special Bank');
});
test('bank authorization and deletion are enforced', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $admin = User::factory()->administrator()->create();
    $bank = Bank::factory()->create();
    $this->actingAs($viewer)->get(route('banks.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('banks.store'), bankData())->assertForbidden();
    $this->actingAs($encoder)->delete(route('banks.destroy', $bank))->assertForbidden();
    $this->actingAs($admin)->delete(route('banks.destroy', $bank))->assertRedirect(route('banks.index'));
    $this->assertModelMissing($bank);
});
test('guests cannot access banks', function () {
    $this->get(route('banks.index'))->assertRedirect(route('login'));
});
