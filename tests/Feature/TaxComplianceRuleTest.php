<?php

use App\Models\BusinessProfile;
use App\Models\TaxComplianceRule;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\TaxRuleRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function complianceTaxProfile(): TaxProfile
{
    $business = BusinessProfile::factory()->active()->create();

    $profile = TaxProfile::query()->create([
        'business_profile_id' => $business->id,
        'taxpayer_type' => 'sole_proprietorship',
        'registration_type' => 'registered',
        'vat_status' => 'non_vat',
        'income_tax_option' => 'graduated',
        'percentage_tax_registered' => true,
        'percentage_tax_rate' => null,
        'filing_frequency' => 'quarterly',
        'registration_start_date' => '2026-05-01',
        'first_filing_period' => '2026-Q2',
        'rdo_code' => '050',
        'tin' => '123-456-789',
        'branch_code' => '00000',
        'registered_books_type' => 'manual',
        'active' => true,
    ]);
    $profile->forms()->create(['form_code' => '2551Q', 'filing_frequency' => 'quarterly', 'active' => true]);

    return $profile;
}

function complianceRuleData(array $changes = []): array
{
    return array_merge([
        'tax_type' => 'percentage_tax',
        'bir_form_number' => '2551Q',
        'form_title' => 'Quarterly Percentage Tax Return',
        'taxpayer_applicability' => 'sole_proprietorship',
        'registration_applicability' => 'registered',
        'filing_frequency' => 'quarterly',
        'effective_from' => '2026-01-01',
        'effective_to' => '2026-12-31',
        'tax_rate' => '3.000000',
        'tax_base_rule' => 'Configured gross taxable sales or receipts.',
        'credit_rule' => 'Configured allowable credits supported by records.',
        'deadline_rule' => 'Review the current official deadline guidance.',
        'deadline_months_after_period_end' => 1,
        'deadline_day' => 25,
        'amendment_supported' => true,
        'attachment_requirements' => ['Supporting sales schedule'],
        'official_reference_title' => 'BIR Form No. 2551Q Guidelines and Instructions',
        'official_reference_url' => 'https://efps.bir.gov.ph/efps-war/EFPSWeb_war/forms2018Version/2551Q/2551q_guidelines.html',
        'last_reviewed_on' => '2026-08-01',
        'reviewer_notes' => 'Reviewed against the official BIR form guidance.',
        'change_reason' => null,
        'active' => true,
    ], $changes);
}

it('creates configurable rules and resolves the effective registered form rule', function (): void {
    $profile = complianceTaxProfile();
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->post(route('tax-rules.store'), complianceRuleData())
        ->assertRedirect(route('tax-rules.index'))
        ->assertSessionHasNoErrors();

    $rule = app(TaxRuleRegistry::class)->resolve($profile, '2551Q', '2026-06-30');

    expect($rule)->not->toBeNull()
        ->and($rule->tax_rate)->toBe('3.000000')
        ->and($rule->tax_base_rule)->toContain('gross taxable sales')
        ->and(app(TaxRuleRegistry::class)->resolve($profile, '2551Q', '2027-01-01'))->toBeNull();

    $profile->forms()->where('form_code', '2551Q')->update(['active' => false]);
    expect(app(TaxRuleRegistry::class)->resolve($profile, '2551Q', '2026-06-30'))->toBeNull();
});

it('blocks overlapping active rules for the same form and profile', function (): void {
    complianceTaxProfile();
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->post(route('tax-rules.store'), complianceRuleData())->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('tax-rules.store'), complianceRuleData([
        'tax_type' => 'other',
        'effective_from' => '2026-06-01',
        'effective_to' => null,
    ]))->assertSessionHasErrors('effective_from');

    expect(TaxComplianceRule::query()->count())->toBe(1);
});

it('blocks activation when an inactive rule would overlap an active rule', function (): void {
    $profile = complianceTaxProfile();
    $user = User::factory()->administrator()->create();
    app(TaxRuleRegistry::class)->create($profile, complianceRuleData(), $user);
    $inactive = app(TaxRuleRegistry::class)->create($profile, complianceRuleData([
        'effective_from' => '2026-06-01',
        'effective_to' => null,
        'active' => false,
    ]), $user);

    $this->actingAs($user)->patch(route('tax-rules.activate', $inactive))
        ->assertSessionHasErrors('effective_from');

    expect($inactive->refresh()->active)->toBeFalse();
});

it('preserves a used rule and requires a reason and reviewer for its successor', function (): void {
    $profile = complianceTaxProfile();
    $user = User::factory()->administrator()->create();
    $rule = app(TaxRuleRegistry::class)->create($profile, complianceRuleData(), $user);
    $rule->update(['used_at' => now()]);

    $this->actingAs($user)->put(route('tax-rules.update', $rule), complianceRuleData(['tax_rate' => '4.000000']))
        ->assertSessionHasErrors('change_reason');
    $this->actingAs($user)->put(route('tax-rules.update', $rule), complianceRuleData([
        'tax_rate' => '4.000000',
        'change_reason' => 'Official rule was reviewed and replaced.',
    ]))->assertSessionHasNoErrors();

    $rule->refresh();
    $successor = TaxComplianceRule::query()->where('supersedes_id', $rule->id)->sole();
    expect($rule->tax_rate)->toBe('3.000000')
        ->and($rule->active)->toBeFalse()
        ->and($successor->tax_rate)->toBe('4.000000')
        ->and($successor->reviewed_by)->toBe($user->id)
        ->and($successor->change_reason)->toBe('Official rule was reviewed and replaced.');
});

it('shows stale official-reference warnings and accepts only official BIR URLs', function (): void {
    $profile = complianceTaxProfile();
    $user = User::factory()->administrator()->create();
    $rule = app(TaxRuleRegistry::class)->create($profile, complianceRuleData([
        'last_reviewed_on' => now()->subDays(366)->toDateString(),
    ]), $user);

    $this->actingAs($user)->get(route('tax-rules.index'))
        ->assertSuccessful()
        ->assertSee('Official-reference review is stale.');
    $this->actingAs($user)->patch(route('tax-rules.review', $rule), [
        'official_reference_title' => $rule->official_reference_title,
        'official_reference_url' => $rule->official_reference_url,
        'last_reviewed_on' => now()->toDateString(),
        'reviewer_notes' => 'Current official reference reconfirmed.',
    ])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('tax-rules.store'), complianceRuleData([
        'bir_form_number' => '1701Q',
        'official_reference_url' => 'https://example.com/1701q',
    ]))->assertSessionHasErrors('official_reference_url');

    expect($rule->refresh()->referenceReviewIsStale())->toBeFalse()
        ->and($rule->reviewed_by)->toBe($user->id);
});

it('seeds all registry permissions and enforces authorization', function (): void {
    complianceTaxProfile();
    $administrator = User::factory()->administrator()->create();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    expect($administrator->can('tax-rules.view'))->toBeTrue()
        ->and($administrator->can('tax-rules.create'))->toBeTrue()
        ->and($administrator->can('tax-rules.update'))->toBeTrue()
        ->and($administrator->can('tax-rules.activate'))->toBeTrue()
        ->and($administrator->can('tax-rules.deactivate'))->toBeTrue()
        ->and($administrator->can('tax-rules.review'))->toBeTrue();

    $this->actingAs($viewer)->get(route('tax-rules.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('tax-rules.store'), complianceRuleData())->assertForbidden();
    auth()->logout();
    $this->get(route('tax-rules.index'))->assertRedirect(route('login'));
});

it('supports every required initial tax type without exposing worksheet calculations', function (): void {
    expect(array_keys(config('tax_compliance.tax_types')))->toBe([
        'percentage_tax',
        'quarterly_income_tax',
        'annual_income_tax',
        'creditable_withholding_tax',
        'percentage_tax_withheld',
        'other',
    ]);

    $taxRuleRoutes = collect(app('router')->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'tax-rules'));

    expect($taxRuleRoutes)->not->toBeEmpty()
        ->and($taxRuleRoutes->contains(fn ($route): bool => str_contains($route->uri(), 'calculate')))->toBeFalse()
        ->and(Schema::hasTable('tax_worksheets'))->toBeFalse();
});
