<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(LazilyRefreshDatabase::class);

test('users can request and complete a secure password reset', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post(route('password.update'), ['token' => $notification->token, 'email' => $user->email, 'password' => 'NewSecurePassword123!', 'password_confirmation' => 'NewSecurePassword123!'])->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('NewSecurePassword123!', $user->fresh()->password))->toBeTrue();
});

test('password reset requests do not disclose unknown accounts', function () {
    $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
});
