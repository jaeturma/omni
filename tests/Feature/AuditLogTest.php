<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['audit.capture_during_tests' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('material creates and updates record actor and before and after values', function () {
    $actor = User::factory()->administrator()->create();
    $this->actingAs($actor);
    $customer = Customer::factory()->create(['name' => 'Before Name']);
    $customer->update(['name' => 'After Name', 'updated_by' => $actor->id]);

    $created = AuditLog::query()->where('event', 'customer.created')->where('subject_id', $customer->id)->firstOrFail();
    $updated = AuditLog::query()->where('event', 'customer.updated')->where('subject_id', $customer->id)->firstOrFail();

    expect($created->actor_id)->toBe($actor->id)
        ->and($updated->before_values['name'])->toBe('Before Name')
        ->and($updated->after_values['name'])->toBe('After Name')
        ->and($updated->correlation_id)->not->toBeEmpty();
});

test('reasons and lifecycle event names are captured', function () {
    $actor = User::factory()->administrator()->create();
    $this->actingAs($actor);
    $invoice = SalesInvoice::factory()->create(['status' => 'posted', 'posted_by' => $actor->id]);
    $invoice->update(['status' => 'voided', 'void_reason' => 'Duplicate invoice', 'voided_by' => $actor->id]);

    $audit = AuditLog::query()->where('event', 'sales_invoice.voided')->where('subject_id', $invoice->id)->firstOrFail();
    expect($audit->reason)->toBe('Duplicate invoice');
});

test('passwords and tokens never leak into audit values', function () {
    $actor = User::factory()->administrator()->create();
    $this->actingAs($actor);
    $user = User::factory()->create(['password' => 'SecretPassword123!', 'remember_token' => 'secret-token']);
    $audit = AuditLog::query()->where('event', 'user.created')->where('subject_id', $user->id)->firstOrFail();

    expect($audit->after_values['password'])->toBe('[REDACTED]')
        ->and($audit->after_values['remember_token'])->toBe('[REDACTED]')
        ->and(json_encode($audit->after_values))->not->toContain('secret-token', 'SecretPassword123!');
});

test('audit records are append only and survive source deletion', function () {
    $actor = User::factory()->administrator()->create();
    $this->actingAs($actor);
    $customer = Customer::factory()->create();
    $audit = AuditLog::query()->where('event', 'customer.created')->where('subject_id', $customer->id)->firstOrFail();
    $customer->delete();

    expect(fn () => $audit->update(['reason' => 'tamper']))->toThrow(LogicException::class)
        ->and(fn () => $audit->delete())->toThrow(LogicException::class)
        ->and($audit->fresh())->not->toBeNull();
});

test('audit review and export are restricted by separate permissions', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $exporter = User::factory()->create();
    $exporter->givePermissionTo(Permission::findByName('audit-logs.export'));

    $this->actingAs($viewer)->get(route('audit-logs.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('audit-logs.export'))->assertForbidden();
    $this->actingAs($exporter)->get(route('audit-logs.export'))->assertDownload();
});

test('authorized reviewers can filter logs and protected metadata remains restricted', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    Customer::factory()->create();

    $this->get(route('audit-logs.index', ['module' => 'customer']))
        ->assertSuccessful()->assertSee('customer.created');

    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo(Permission::findByName('audit-logs.view'));
    $log = AuditLog::query()->where('event', 'customer.created')->firstOrFail();
    $log->forceFill(['ip_address' => '10.0.0.8'])->saveQuietly();
    $this->actingAs($reviewer)->get(route('audit-logs.show', $log))
        ->assertSuccessful()->assertSee('Protected')->assertDontSee('10.0.0.8');
});

test('failed authentication is audited without the submitted password', function () {
    $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'NeverStoreThis123!'])->assertSessionHasErrors('email');
    $audit = AuditLog::query()->where('event', 'auth.login_failed')->firstOrFail();

    expect(json_encode($audit->toArray()))->not->toContain('NeverStoreThis123!');
});
