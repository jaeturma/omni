<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function systemSettingsData(array $changes = []): array
{
    return array_merge(SystemSettings::DEFAULTS, $changes);
}

test('safe typed defaults are available through the central settings service', function () {
    $settings = app(SystemSettings::class);

    expect($settings->get('default_currency'))->toBe('PHP')
        ->and($settings->get('records_per_page'))->toBe(25)
        ->and($settings->get('low_stock_default_threshold'))->toBe('5.0000')
        ->and(SystemSetting::query()->count())->toBe(0);
});

test('authorized users can view and update all controlled settings', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('system-settings.edit'))->assertSuccessful()->assertSee('Credentials, API keys');

    $this->actingAs($admin)->put(route('system-settings.update'), ['settings' => systemSettingsData(['application_display_name' => 'Omni Office', 'records_per_page' => 50, 'decimal_places' => 3])])->assertSessionHasNoErrors();

    $settings = app(SystemSettings::class);
    expect(SystemSetting::query()->count())->toBe(count(SystemSettings::DEFAULTS))
        ->and($settings->get('application_display_name'))->toBe('Omni Office')
        ->and($settings->get('records_per_page'))->toBe(50)
        ->and($settings->get('decimal_places'))->toBe(3);
});

test('view-only users cannot update settings', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('system-settings.edit'))->assertSuccessful();
    $this->actingAs($viewer)->put(route('system-settings.update'), ['settings' => systemSettingsData()])->assertForbidden();
});

test('typed range and format validation is enforced', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->put(route('system-settings.update'), ['settings' => systemSettingsData([
        'default_currency' => 'PESO', 'decimal_places' => 5, 'records_per_page' => 500,
        'low_stock_default_threshold' => '-1', 'maintenance_contact_email' => 'invalid',
    ])])->assertSessionHasErrors([
        'settings.default_currency', 'settings.decimal_places', 'settings.records_per_page',
        'settings.low_stock_default_threshold', 'settings.maintenance_contact_email',
    ]);
});

test('arbitrary and secret-like keys cannot be created', function () {
    $admin = User::factory()->administrator()->create();
    $payload = systemSettingsData() + ['smtp_password' => 'secret', 'api_key' => 'secret'];

    $this->actingAs($admin)->put(route('system-settings.update'), ['settings' => $payload])->assertSessionHasErrors('settings');
    expect(SystemSetting::query()->count())->toBe(0)
        ->and(fn () => app(SystemSettings::class)->get('api_key'))->toThrow(InvalidArgumentException::class);
});

test('guests cannot access system settings', function () {
    $this->get(route('system-settings.edit'))->assertRedirect(route('login'));
});
