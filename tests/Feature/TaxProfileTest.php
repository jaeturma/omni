<?php

use App\Models\BusinessProfile;
use App\Models\TaxProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function taxProfileData(array $changes = []): array
{
    return array_merge(['taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'percentage_tax_rate' => '3.000000', 'percentage_tax_effective_from' => '2026-05-01', 'percentage_tax_effective_to' => null, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-05-01', 'first_filing_period' => '2026-Q2', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'notes' => null, 'active' => true, 'forms' => ['2551Q']], $changes);
}

test('authorized users can create and update one active tax profile linked to the active business', function () {
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $this->actingAs($user)->get(route('tax-profile.edit'))->assertSuccessful()->assertSee('Tax-preparation only');
    $this->actingAs($user)->put(route('tax-profile.update'), taxProfileData())->assertSessionHasNoErrors();
    $this->actingAs($user)->put(route('tax-profile.update'), taxProfileData(['notes' => 'Reviewed']))->assertSessionHasNoErrors();
    $profile = TaxProfile::query()->sole();
    expect($profile->business_profile_id)->toBe($business->id)->and($profile->percentage_tax_rate)->toBe('3.000000')->and($profile->forms()->where('form_code', '2551Q')->exists())->toBeTrue();
});

test('guests cannot access tax configuration', function () {
    $this->get(route('tax-profile.edit'))->assertRedirect(route('login'));
    $this->put(route('tax-profile.update'), taxProfileData())->assertRedirect(route('login'));
});

test('tax profile validation rejects invalid effective dates and rates', function () {
    $user = User::factory()->administrator()->create();
    BusinessProfile::factory()->active()->create();
    $this->actingAs($user)->put(route('tax-profile.update'), taxProfileData(['percentage_tax_rate' => '1.1234567', 'percentage_tax_effective_to' => '2026-04-01']))->assertSessionHasErrors(['percentage_tax_rate', 'percentage_tax_effective_to']);
});

test('effective tax rate periods cannot overlap and historical rates remain', function () {
    $user = User::factory()->administrator()->create();
    BusinessProfile::factory()->active()->create();
    $this->actingAs($user)->put(route('tax-profile.update'), taxProfileData())->assertSessionHasNoErrors();
    $rate = ['tax_type' => 'percentage_tax', 'rate' => '3.000000', 'effective_from' => '2026-05-01', 'effective_to' => '2026-12-31'];
    $this->actingAs($user)->post(route('tax-profile.rates.store'), $rate)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('tax-profile.rates.store'), array_merge($rate, ['effective_from' => '2026-06-01']))->assertSessionHasErrors('effective_from');
    expect(TaxProfile::query()->sole()->rates()->count())->toBe(1);
});

test('tax configuration does not expose a tax calculation endpoint', function () {
    expect(collect(app('router')->getRoutes())->contains(fn ($route) => str_contains($route->uri(), 'calculate')))->toBeFalse();
});
