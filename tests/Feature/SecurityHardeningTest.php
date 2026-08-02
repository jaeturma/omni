<?php

use App\Models\Quotation;
use App\Models\SalesAttachment;
use App\Models\User;
use App\Services\UserSessionManager;
use App\Support\SensitiveData;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests and inactive users cannot access protected financial routes', function () {
    $this->get(route('financial-dashboard'))->assertRedirect(route('login'));

    $inactive = User::factory()->inactive()->create();
    $this->actingAs($inactive)->get(route('financial-dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a user management permission cannot be used to assign privileged roles', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(Permission::findByName('users.manage'));

    $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Escalated User', 'email' => 'escalated@example.com',
        'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!',
        'active' => true, 'roles' => ['Administrator'],
    ])->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'escalated@example.com')->exists())->toBeFalse();
});

test('password changes revoke database sessions and remember tokens', function () {
    config(['session.driver' => 'database', 'session.table' => 'sessions']);
    $user = User::factory()->create(['remember_token' => 'remember-me']);
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'payload', 'last_activity' => now()->timestamp]);

    app(UserSessionManager::class)->invalidate($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->toBeNull();
});

test('private attachments require permission to view their related record', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findByName('sales-attachments.view'));
    $quotation = Quotation::factory()->create();
    $attachment = SalesAttachment::query()->create([
        'attachable_type' => $quotation->getMorphClass(), 'attachable_id' => $quotation->id,
        'document_type' => 'other', 'original_filename' => 'private.pdf',
        'stored_filename' => 'private/private.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 10, 'file_hash' => hash('sha256', 'private'), 'uploaded_by' => $quotation->created_by,
        'document_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)->get(route('sales-attachments.download', $attachment))->assertForbidden();
});

test('sensitive values are masked consistently', function () {
    expect(SensitiveData::mask('123-456-789-00000'))->toBe('*************0000')
        ->and(SensitiveData::mask(null, 4, 'No TIN'))->toBe('No TIN');
});

test('authentication endpoints are rate limited', function () {
    foreach (range(1, 6) as $attempt) {
        $response = $this->post(route('login.store'), ['email' => 'missing@example.com', 'password' => 'WrongPassword123!']);
    }

    $response->assertTooManyRequests();
});

test('production configuration disables debug and secure responses carry headers', function () {
    expect(file_get_contents(config_path('app.php')))->toContain("env('APP_ENV', 'production') === 'production' ? false")
        ->and(file_get_contents(config_path('session.php')))->toContain("env('APP_ENV', 'production') === 'production'");

    $this->withServerVariables(['HTTPS' => 'on'])->get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Strict-Transport-Security');
});
