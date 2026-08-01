<?php

use App\Models\BusinessProfile;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\TaxComplianceCalendar;
use App\Services\TaxRuleRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function calendarProfile(array $forms = ['2551Q', '1701Q', '1701']): TaxProfile
{
    $business = BusinessProfile::factory()->active()->create();
    $profile = TaxProfile::query()->create([
        'business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered',
        'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true,
        'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-05-01', 'first_filing_period' => '2026-Q2',
        'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true,
    ]);
    foreach ($forms as $form) {
        $profile->forms()->create(['form_code' => $form, 'filing_frequency' => $form === '1701' ? 'annual' : 'quarterly', 'active' => true]);
    }

    return $profile;
}

function calendarRuleData(string $form, array $changes = []): array
{
    return array_merge([
        'tax_type' => 'percentage_tax', 'bir_form_number' => $form, 'form_title' => "BIR Form $form",
        'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered',
        'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3, 4], 'effective_from' => '2026-01-01',
        'effective_to' => null, 'tax_rate' => null, 'tax_base_rule' => 'Configured tax base.', 'credit_rule' => 'Configured credits.',
        'deadline_rule' => 'One month after the official quarter, on configured day 25.',
        'deadline_months_after_period_end' => 1, 'deadline_day' => 25, 'amendment_supported' => true,
        'attachment_requirements' => [], 'official_reference_title' => "Official BIR Form $form reference",
        'official_reference_url' => 'https://efps.bir.gov.ph/efps-war/EFPSWeb_war/forms2018Version/2551Q/2551q_guidelines.html',
        'last_reviewed_on' => '2026-08-01', 'reviewer_notes' => 'Reviewed.', 'active' => true,
    ], $changes);
}

function seedCalendarRules(TaxProfile $profile, User $reviewer): void
{
    $registry = app(TaxRuleRegistry::class);
    $registry->create($profile, calendarRuleData('2551Q'), $reviewer);
    $registry->create($profile, calendarRuleData('1701Q', [
        'tax_type' => 'quarterly_income_tax', 'applicable_quarters' => [1, 2, 3],
        'deadline_rule' => 'Two months after quarter end, on configured day 15.',
        'deadline_months_after_period_end' => 2, 'deadline_day' => 15,
    ]), $reviewer);
    $registry->create($profile, calendarRuleData('1701', [
        'tax_type' => 'annual_income_tax', 'filing_frequency' => 'annual', 'applicable_quarters' => null,
        'deadline_rule' => 'Four months after year end, on configured day 15.',
        'deadline_months_after_period_end' => 4, 'deadline_day' => 15,
    ]), $reviewer);
}

it('generates registered obligations with an official Q2 and partial capture start', function (): void {
    $profile = calendarProfile();
    $user = User::factory()->administrator()->create();
    seedCalendarRules($profile, $user);

    $this->actingAs($user)->post(route('tax-calendar.generate'), ['from_year' => 2026, 'through_year' => 2026])
        ->assertSessionHasNoErrors();

    $q2 = TaxPeriod::query()->where('frequency', 'quarterly')->where('quarter', 2)->sole();
    expect($q2->period_start->toDateString())->toBe('2026-04-01')
        ->and($q2->period_end->toDateString())->toBe('2026-06-30')
        ->and($q2->capture_start->toDateString())->toBe('2026-05-01')
        ->and(TaxObligation::query()->count())->toBe(6)
        ->and(TaxObligation::query()->where('bir_form_number', '1701Q')->count())->toBe(2)
        ->and(TaxObligation::query()->where('bir_form_number', '1701')->count())->toBe(1)
        ->and(TaxPeriod::query()->where('frequency', 'annual')->sole()->capture_start->toDateString())->toBe('2026-05-01')
        ->and(app(TaxComplianceCalendar::class)->generate($profile, 2026, 2026))->toBe(0);
});

it('generates only forms registered on the active tax profile', function (): void {
    $profile = calendarProfile(['2551Q']);
    $user = User::factory()->administrator()->create();
    app(TaxRuleRegistry::class)->create($profile, calendarRuleData('2551Q'), $user);
    app(TaxRuleRegistry::class)->create($profile, calendarRuleData('1701Q', ['tax_type' => 'quarterly_income_tax']), $user);

    expect(app(TaxComplianceCalendar::class)->generate($profile, 2026, 2026))->toBe(3)
        ->and(TaxObligation::query()->pluck('bir_form_number')->unique()->all())->toBe(['2551Q']);
});

it('derives deadlines from rule configuration and preserves its snapshot', function (): void {
    $profile = calendarProfile(['2551Q']);
    $user = User::factory()->administrator()->create();
    app(TaxRuleRegistry::class)->create($profile, calendarRuleData('2551Q'), $user);
    app(TaxComplianceCalendar::class)->generate($profile, 2026, 2026);

    $obligation = TaxObligation::query()->whereHas('taxPeriod', fn ($query) => $query->where('quarter', 2))->sole();
    expect($obligation->original_due_date->toDateString())->toBe('2026-07-25')
        ->and($obligation->adjusted_due_date)->toBeNull()
        ->and($obligation->rule_snapshot['deadline_day'])->toBe(25)
        ->and($obligation->deadline_rule_source)->toContain('configured day 25');
});

it('records every deadline adjustment without changing the original due date', function (): void {
    $profile = calendarProfile(['2551Q']);
    $user = User::factory()->administrator()->create();
    app(TaxRuleRegistry::class)->create($profile, calendarRuleData('2551Q'), $user);
    app(TaxComplianceCalendar::class)->generate($profile, 2026, 2026);
    $obligation = TaxObligation::query()->firstOrFail();
    $adjustment = ['adjusted_due_date' => '2026-07-31', 'reason' => 'Official extension', 'source_title' => 'BIR official extension notice', 'source_url' => 'https://www.bir.gov.ph/'];

    $this->actingAs($user)->post(route('tax-calendar.deadline-adjustments.store', $obligation), $adjustment)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('tax-calendar.deadline-adjustments.store', $obligation), array_merge($adjustment, ['adjusted_due_date' => '2026-08-05']))->assertSessionHasNoErrors();

    $obligation->refresh();
    expect($obligation->original_due_date->toDateString())->toBe('2026-07-25')
        ->and($obligation->adjusted_due_date->toDateString())->toBe('2026-08-05')
        ->and($obligation->deadlineAdjustments()->count())->toBe(2)
        ->and($obligation->deadlineAdjustments()->latest('id')->firstOrFail()->previous_due_date->toDateString())->toBe('2026-07-31');
});

it('enforces preparation status transitions and reviewer assignment', function (): void {
    $profile = calendarProfile(['2551Q']);
    $user = User::factory()->administrator()->create();
    $reviewer = User::factory()->create();
    app(TaxRuleRegistry::class)->create($profile, calendarRuleData('2551Q'), $user);
    app(TaxComplianceCalendar::class)->generate($profile, 2026, 2026);
    $obligation = TaxObligation::query()->whereHas('taxPeriod', fn ($query) => $query->where('quarter', 2))->sole();

    $this->actingAs($user)->patch(route('tax-calendar.update', $obligation), ['status' => 'filed'])->assertSessionHasErrors('status');
    $this->actingAs($user)->patch(route('tax-calendar.update', $obligation), ['status' => 'preparing', 'assigned_reviewer_id' => $reviewer->id, 'notes' => 'Prepare supporting records.'])->assertSessionHasNoErrors();
    $this->actingAs($user)->patch(route('tax-calendar.update', $obligation), ['status' => 'for_review'])->assertSessionHasNoErrors();

    expect($obligation->refresh()->status)->toBe('for_review')
        ->and($obligation->assigned_reviewer_id)->toBe($reviewer->id)
        ->and($obligation->notes)->toBe('Prepare supporting records.');
});

it('seeds calendar permissions and blocks unauthorized generation and updates', function (): void {
    calendarProfile();
    $administrator = User::factory()->administrator()->create();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    expect($administrator->can('tax-calendar.view'))->toBeTrue()
        ->and($administrator->can('tax-calendar.generate'))->toBeTrue()
        ->and($administrator->can('tax-calendar.update'))->toBeTrue()
        ->and($administrator->can('tax-calendar.assign-reviewer'))->toBeTrue();
    $this->actingAs($viewer)->get(route('tax-calendar.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('tax-calendar.generate'), ['from_year' => 2026, 'through_year' => 2026])->assertForbidden();
});
