<?php

use App\Logging\RedactSensitiveContext;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\RecordArchive;
use App\Models\RetentionPolicy;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DataClassificationRegistry;
use Database\Seeders\PrivacyRetentionPolicySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Monolog\Level;
use Monolog\LogRecord;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['audit.capture_during_tests' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PrivacyRetentionPolicySeeder::class);
});

test('classification registry and explicit default retention rules are available', function () {
    expect(array_keys(app(DataClassificationRegistry::class)->all()))->toBe(['public', 'internal', 'confidential', 'restricted'])
        ->and(RetentionPolicy::query()->where('record_type', 'financial_transactions')->value('retention_months'))->toBe(120)
        ->and(RetentionPolicy::query()->where('record_type', 'audit_logs')->value('disposition'))->toBe('retain_permanently');
});

test('data subject lookup masks contacts addresses and tins without sensitive permission', function () {
    $customer = Customer::factory()->create(['name' => 'Privacy Subject', 'email' => 'subject@example.com', 'phone' => '09171234567', 'address' => 'Private Address', 'tin' => '123-456-789-000']);
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo(Permission::findByName('privacy-settings.view'));

    $this->actingAs($reviewer)->get(route('privacy.index', ['q' => 'Privacy Subject']))->assertSuccessful()
        ->assertSee('s******@example.com')->assertSee('********567')->assertSee('[PROTECTED]')
        ->assertSee('***********-000', false)->assertDontSee($customer->email)->assertDontSee($customer->address);
});

test('sensitive exports require separate permission and audit the disclosure', function () {
    Customer::factory()->create(['name' => 'Export Subject', 'email' => 'export@example.com']);
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo(Permission::findByName('privacy-settings.view'));
    $exporter = User::factory()->create();
    $exporter->givePermissionTo(Permission::findByName('sensitive-data.export'));

    $this->actingAs($reviewer)->get(route('privacy.data-subjects.export', ['q' => 'Export Subject']))->assertForbidden();
    $this->actingAs($exporter)->get(route('privacy.data-subjects.export', ['q' => 'Export Subject']))->assertDownload();
    expect(AuditLog::query()->where('event', 'privacy.data_subject_exported')->exists())->toBeTrue();
});

test('retention policy validation prevents ambiguous or unsupported rules', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->post(route('privacy.retention-policies.store'), [
        'record_type' => 'short_rule', 'classification' => 'secret', 'retention_months' => null,
        'retention_trigger' => 'creation', 'disposition' => 'delete', 'legal_basis' => 'Too short',
        'active' => true, 'reviewed_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors(['classification', 'retention_months', 'disposition', 'legal_basis', 'reviewed_at']);
});

test('financial history prevents casual deletion of its customer master', function () {
    $admin = User::factory()->administrator()->create();
    $customer = Customer::factory()->create();
    SalesInvoice::factory()->for($customer)->create();

    $this->actingAs($admin)->delete(route('customers.destroy', $customer))->assertForbidden();
    $this->assertModelExists($customer);
});

test('inactive masters can be archived without deletion and active masters are rejected', function () {
    $admin = User::factory()->administrator()->create();
    $active = Customer::factory()->create(['status' => 'active']);
    $inactive = Customer::factory()->create(['status' => 'inactive']);

    $this->actingAs($admin)->post(route('privacy.archive', ['type' => 'customer', 'id' => $active]), ['reason' => 'No longer an active customer'])->assertSessionHasErrors('record');
    $this->actingAs($admin)->post(route('privacy.archive', ['type' => 'customer', 'id' => $inactive]), ['reason' => 'No longer an active customer'])->assertSessionHasNoErrors();

    expect(RecordArchive::query()->where('subject_id', $inactive->id)->exists())->toBeTrue()
        ->and($inactive->fresh())->not->toBeNull()
        ->and(AuditLog::query()->where('event', 'customer.archived')->where('subject_id', $inactive->id)->exists())->toBeTrue();
});

test('application and audit log context removes secrets and unnecessary personal data', function () {
    $record = new LogRecord(now()->toDateTimeImmutable(), 'test', Level::Info, 'message', ['password' => 'secret', 'email' => 'person@example.com', 'nested' => ['token' => 'abc'], 'record_id' => 42]);
    $redacted = app(RedactSensitiveContext::class)($record);
    app(AuditLogger::class)->log('privacy.tested', metadata: ['email' => 'person@example.com', 'address' => 'Private Address']);
    $audit = AuditLog::query()->where('event', 'privacy.tested')->firstOrFail();

    expect($redacted->context)->toBe(['password' => '[REDACTED]', 'email' => '[REDACTED]', 'nested' => ['token' => '[REDACTED]'], 'record_id' => 42])
        ->and($audit->protected_metadata)->toBe(['email' => '[PROTECTED]', 'address' => '[PROTECTED]']);
});

test('privacy settings are unavailable to ordinary viewers', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('privacy.index'))->assertForbidden();
});
