<?php

use App\Actions\IssueDocumentNumber;
use App\Models\BusinessProfile;
use App\Models\DocumentNumberReservation;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function makeDocumentSequence(array $changes = []): DocumentSequence
{
    $user = $changes['user'] ?? User::factory()->administrator()->create();
    $business = $changes['business'] ?? BusinessProfile::factory()->create();
    $year = $changes['year'] ?? FiscalYear::factory()->for($business)->for($user, 'creator')->create();
    unset($changes['user'], $changes['business'], $changes['year']);

    return DocumentSequence::query()->create(array_merge([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'sales_invoice', 'prefix' => 'SI-{YYYY}-', 'suffix' => '', 'current_number' => 0,
        'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
    ], $changes));
}

function documentSequenceData(FiscalYear $year, array $changes = []): array
{
    return array_merge(['document_type' => 'sales_invoice', 'prefix' => 'SI-{YYYY}-', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'fiscal_year', 'fiscal_year_id' => $year->id, 'active' => true], $changes);
}

test('sequence definitions are configurable with fiscal year formatting and padding', function () {
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-05-01', 'ends_on' => '2026-12-31']);

    $this->actingAs($user)->post(route('document-sequences.store'), documentSequenceData($year))->assertRedirect()->assertSessionHasNoErrors();
    $sequence = DocumentSequence::query()->sole();
    expect($sequence->preview())->toBe('SI-2026-000001')->and($sequence->current_number)->toBe(0);

    $this->actingAs($user)->put(route('document-sequences.update', $sequence), documentSequenceData($year, ['prefix' => 'INV-{YYYY}-', 'suffix' => '-A', 'padding' => 4]))->assertSessionHasNoErrors();
    expect($sequence->fresh()->preview())->toBe('INV-2026-0001-A');
});

test('preview does not consume and issuance is sequential with preserved history', function () {
    $user = User::factory()->administrator()->create();
    $sequence = makeDocumentSequence(['user' => $user]);
    $preview = $sequence->preview();
    $issuer = new IssueDocumentNumber;

    $first = $issuer->handle($sequence, $user->id);
    $second = $issuer->handle($sequence, $user->id);

    expect($preview)->toBe('SI-2026-000001')
        ->and($first->document_number)->toBe('SI-2026-000001')
        ->and($second->document_number)->toBe('SI-2026-000002')
        ->and($sequence->fresh()->current_number)->toBe(2)
        ->and($sequence->reservations()->count())->toBe(2);
});

test('repeated transaction-safe issuance produces no duplicate numbers', function () {
    $user = User::factory()->administrator()->create();
    $sequence = makeDocumentSequence(['user' => $user, 'document_type' => 'collection_receipt', 'prefix' => 'CR-{YYYY}-']);
    $issuer = new IssueDocumentNumber;

    foreach (range(1, 20) as $unused) {
        $issuer->handle($sequence, $user->id);
    }

    expect(DocumentNumberReservation::query()->count())->toBe(20)
        ->and(DocumentNumberReservation::query()->distinct()->count('document_number'))->toBe(20)
        ->and($sequence->fresh()->current_number)->toBe(20);
});

test('database constraints prevent duplicate definitions and issued numbers', function () {
    $sequence = makeDocumentSequence();

    expect(fn () => DocumentSequence::query()->create($sequence->only(['business_profile_id', 'fiscal_year_id', 'fiscal_year_scope', 'document_type', 'prefix', 'suffix', 'current_number', 'padding', 'reset_rule', 'active', 'created_by', 'updated_by'])))->toThrow(QueryException::class);
});

test('inactive sequences reject issuance', function () {
    $user = User::factory()->administrator()->create();
    $sequence = makeDocumentSequence(['user' => $user, 'active' => false]);

    $this->actingAs($user)->post(route('document-sequences.issue', $sequence))->assertSessionHasErrors('issue');
    expect($sequence->reservations()->count())->toBe(0)->and($sequence->fresh()->current_number)->toBe(0);
});

test('validation requires fiscal linkage for yearly formatting and protects issued history', function () {
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create();
    $sequence = makeDocumentSequence(['user' => $user, 'business' => $business, 'year' => $year]);
    (new IssueDocumentNumber)->handle($sequence, $user->id);

    $this->actingAs($user)->post(route('document-sequences.store'), documentSequenceData($year, ['document_type' => 'journal_entry', 'fiscal_year_id' => null]))->assertSessionHasErrors('fiscal_year_id');
    $this->actingAs($user)->put(route('document-sequences.update', $sequence), documentSequenceData($year, ['current_number' => 0]))->assertSessionHasErrors('current_number');
});

test('guests cannot manage or issue document sequences', function () {
    $sequence = makeDocumentSequence();
    $this->get(route('document-sequences.index'))->assertRedirect(route('login'));
    $this->post(route('document-sequences.issue', $sequence))->assertRedirect(route('login'));
});
