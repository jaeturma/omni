# WP-09-01 — Tax Compliance Settings and Rule Registry

## Objective

Create the configurable tax-rule registry and compliance settings used by all Phase 9 worksheets.

## Read First

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/DEVELOPMENT_RULES.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md

## Scope

Create or extend records for:

- tax type
- BIR form number
- form title
- taxpayer applicability
- filing frequency
- effective dates
- tax rate
- tax base
- deduction or credit treatment
- deadline rule
- amendment support
- attachment requirements
- official reference title
- official reference URL metadata
- last reviewed date
- active status
- reviewer notes

## Required Initial Tax Types

- percentage_tax
- quarterly_income_tax
- annual_income_tax
- creditable_withholding_tax
- percentage_tax_withheld
- other configurable tax type

## Functional Requirements

- Effective-date tax rules.
- No overlapping active rules for the same form and taxpayer profile.
- Separate tax rate, tax base, deadline, and credit rules.
- Preserve historical rules used by completed worksheets.
- Support registered and non-registered forms.
- Require a reason and reviewer when changing a previously used rule.
- Show a warning when official-reference review is stale.
- Do not calculate returns in this work package.

## Permissions

- tax-rules.view
- tax-rules.create
- tax-rules.update
- tax-rules.activate
- tax-rules.deactivate
- tax-rules.review

## Tests

- Effective-date resolution
- Overlap prevention
- Historical-rule preservation
- Registered-form applicability
- Stale-reference warning
- Authorization
- No worksheet calculation

## Acceptance Criteria

1. Tax rules are configurable and effective-dated.
2. Historical rules are preserved.
3. Overlapping rules are blocked.
4. Official-reference metadata is reviewable.
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
