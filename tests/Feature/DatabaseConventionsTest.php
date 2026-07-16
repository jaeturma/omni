<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

test('the baseline framework schema migrates with its required tables and columns', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasColumns('users', [
            'id',
            'name',
            'email',
            'password',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});
