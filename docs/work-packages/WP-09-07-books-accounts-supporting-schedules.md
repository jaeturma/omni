# WP-09-07 — Books of Accounts and Supporting Schedules

## Objective

Generate reviewable books-of-accounts exports and supporting schedules from posted records while respecting the taxpayer's registered book type.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-09-BIR-TAX-PREPARATION-AND-COMPLIANCE.md
- docs/work-packages/WP-09-06-withholding-certificates-tax-credits.md

## Scope

Generate exports for:

- General journal
- General ledger
- Cash receipts book
- Cash disbursements book
- Sales book
- Purchase book
- Expense book
- Inventory book or stock ledger
- Accounts receivable schedule
- Accounts payable schedule
- Withholding certificate schedule
- Tax-payment schedule
- Invoice-sequence report
- Annual inventory listing support schedule

## Functional Requirements

- Use posted records only.
- Support date range, fiscal year, and tax period.
- Display business identity and report parameters.
- Include beginning and ending balances where applicable.
- Support CSV and print/PDF-ready output.
- Preserve generated-by and generated-at information.
- Mark outputs as internal, manual-book support, loose-leaf draft, or computerized-book export according to registered configuration.
- Do not claim that exports are approved BIR books.
- Show a compliance warning when registered-book configuration is missing.
- Keep sequence and pagination deterministic.

## Permissions

- books-of-accounts.view
- books-of-accounts.export
- tax-schedules.view
- tax-schedules.export

## Tests

- Posted-record inclusion
- Voided exclusion
- Beginning and ending balances
- Report parameters
- Registered-book warning
- Export reconciliation
- Authorization

## Acceptance Criteria

1. Required books and schedules are exportable.
2. Totals reconcile to accounting records.
3. Registered-book status is clearly disclosed.
4. Outputs do not claim automatic BIR approval.
5. Tests pass.

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
