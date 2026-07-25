# WP-09-02 — Tax Periods and Compliance Calendar

## Objective

Create tax periods and a compliance calendar from the registered tax profile and effective tax rules.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-01-tax-compliance-settings-rule-registry.md

## Scope

Create tax-period and obligation records containing:

- tax type
- BIR form
- period start and end
- quarter or year
- original due date
- adjusted due date
- deadline-rule source
- filing status
- payment status
- amendment status
- assigned reviewer
- notes
- created rule snapshot

## Initial Period Support

- Q2 2026 beginning from actual business registration or start date for transaction capture
- Q3 2026
- Q4 2026
- Future quarterly periods
- Annual periods
- First, second, and third quarterly income-tax periods where applicable

## Functional Requirements

- Generate obligations only for registered applicable forms.
- Preserve official calendar-quarter definitions.
- Support partial first operating period without redefining the official quarter.
- Allow deadline adjustment records with reason and source.
- Statuses: upcoming, open, preparing, for_review, ready_to_file, filed, paid, amended, overdue, not_applicable.
- Do not silently change historical due dates.
- Show reminders without sending external notifications unless existing infrastructure supports it.
- Do not directly file returns.

## Permissions

- tax-calendar.view
- tax-calendar.generate
- tax-calendar.update
- tax-calendar.assign-reviewer

## Tests

- Initial partial operating period
- Calendar-quarter preservation
- Applicable-form generation
- Deadline rule
- Historical due-date preservation
- Status transitions
- Authorization

## Acceptance Criteria

1. Tax periods reflect registered obligations.
2. Partial first operation is represented correctly.
3. Deadlines and adjustments are traceable.
4. Statuses support preparation through amendment.
5. Tests and fresh migrations pass.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, services/actions, and focused Pest tests.
- Use posted accounting and validated operational records as sources.
- Use decimal-safe calculations.
- Keep tax rules, rates, form registrations, deadlines, and mappings configurable and effective-dated.
- Preserve every worksheet parameter, source transaction, adjustment, reviewer action, filing reference, and attachment.
- Treat generated figures as preparation worksheets subject to owner or qualified tax-professional review.
- Do not claim that the application directly files or pays taxes through BIR.
- Do not hard-code temporary tax rates or filing deadlines into transaction logic.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
