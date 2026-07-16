<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function businessProfileData(array $overrides = []): array
{
    return array_merge([
        'registered_business_name' => 'Omni ICT Solutions',
        'trade_name' => 'Omni',
        'proprietor_name' => 'Juan Dela Cruz',
        'tin' => '123-456-789',
        'branch_code' => '00000',
        'rdo_code' => '050',
        'registration_date' => '2026-05-01',
        'business_start_date' => '2026-05-01',
        'registered_address' => '123 Rizal Street, Manila, Philippines',
        'email' => 'owner@omni.app',
        'phone' => '09171234567',
        'website' => 'https://omni.app',
        'default_currency' => 'PHP',
        'timezone' => 'Asia/Manila',
        'fiscal_year_start_month' => 1,
        'active' => true,
    ], $overrides);
}

test('authenticated users can view and create the business profile', function () {
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->get(route('business-profile.edit'))->assertSuccessful();
    $this->actingAs($user)->put(route('business-profile.update'), businessProfileData())
        ->assertRedirect(route('business-profile.edit'));

    expect(BusinessProfile::active()->sole()->trade_name)->toBe('Omni');
});

test('authenticated users can update the active profile and the shell uses its name', function () {
    $user = User::factory()->administrator()->create();
    BusinessProfile::factory()->active()->create();

    $this->actingAs($user)->put(route('business-profile.update'), businessProfileData(['trade_name' => 'Omni Trading']))
        ->assertRedirect(route('business-profile.edit'));

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Omni Trading');
});

test('guests cannot manage the business profile', function () {
    $this->get(route('business-profile.edit'))->assertRedirect(route('login'));
    $this->put(route('business-profile.update'), businessProfileData())->assertRedirect(route('login'));
});

test('required identity fields and reasonable tin codes are validated', function () {
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->put(route('business-profile.update'), businessProfileData([
        'registered_business_name' => '',
        'tin' => 'invalid',
        'branch_code' => '12',
    ]))->assertSessionHasErrors(['registered_business_name', 'tin', 'branch_code']);
});

test('the database prevents more than one active business profile', function () {
    BusinessProfile::factory()->active()->create();

    expect(fn () => BusinessProfile::factory()->active()->create())->toThrow(QueryException::class);
});

test('invalid logos are rejected and valid logos are stored', function () {
    Storage::fake('public');
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->put(route('business-profile.update'), businessProfileData([
        'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors('logo');

    $this->actingAs($user)->put(route('business-profile.update'), businessProfileData([
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]))->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists(BusinessProfile::active()->sole()->logo_path);
});
