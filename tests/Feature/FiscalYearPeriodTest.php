<?php

use App\Models\BusinessProfile;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function fiscalYearData(array $changes = []): array
{
    return array_merge(['name' => 'FY 2026', 'starts_on' => '2026-05-01', 'ends_on' => '2026-12-31', 'is_current' => true], $changes);
}

test('an authorized user creates a partial fiscal year with monthly calendar quarters', function () {
    $user = User::factory()->administrator()->create();
    BusinessProfile::factory()->active()->create();

    $this->actingAs($user)->post(route('fiscal-years.store'), fiscalYearData())->assertSessionHasNoErrors();

    $year = FiscalYear::query()->with('periods')->sole();
    expect($year->is_current)->toBeTrue()
        ->and($year->periods)->toHaveCount(8)
        ->and($year->periods->first()->starts_on->toDateString())->toBe('2026-05-01')
        ->and($year->periods->first()->calendar_quarter)->toBe(2)
        ->and($year->periods->last()->ends_on->toDateString())->toBe('2026-12-31')
        ->and($year->periods->pluck('calendar_quarter')->unique()->values()->all())->toBe([2, 3, 4]);
});

test('overlapping fiscal years are rejected', function () {
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);

    $this->actingAs($user)->post(route('fiscal-years.store'), fiscalYearData(['starts_on' => '2026-12-01', 'ends_on' => '2027-11-30']))
        ->assertSessionHasErrors('starts_on');
});

test('only the newest selected fiscal year remains current', function () {
    $user = User::factory()->administrator()->create();
    BusinessProfile::factory()->active()->create();
    $this->actingAs($user)->post(route('fiscal-years.store'), fiscalYearData())->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('fiscal-years.store'), fiscalYearData(['name' => 'FY 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']))->assertSessionHasNoErrors();

    expect(FiscalYear::query()->where('is_current', true)->count())->toBe(1)
        ->and(FiscalYear::query()->where('is_current', true)->sole()->name)->toBe('FY 2027');
});

test('guests cannot view years or change period status', function () {
    $period = FiscalPeriod::factory()->create();
    $this->get(route('fiscal-years.index'))->assertRedirect(route('login'));
    $this->patch(route('fiscal-periods.status.update', $period), ['status' => 'closed'])->assertRedirect(route('login'));
});

test('periods must be closed before locking and locked periods cannot change', function () {
    $user = User::factory()->administrator()->create();
    $period = FiscalPeriod::factory()->create();

    $this->actingAs($user)->patch(route('fiscal-periods.status.update', $period), ['status' => 'locked'])->assertSessionHasErrors('status');
    $this->actingAs($user)->patch(route('fiscal-periods.status.update', $period), ['status' => 'closed'])->assertSessionHasNoErrors();
    $this->actingAs($user)->patch(route('fiscal-periods.status.update', $period), ['status' => 'locked'])->assertSessionHasNoErrors();
    $this->actingAs($user)->patch(route('fiscal-periods.status.update', $period), ['status' => 'closed'])->assertSessionHasErrors('status');

    expect($period->fresh()->status)->toBe('locked');
});
