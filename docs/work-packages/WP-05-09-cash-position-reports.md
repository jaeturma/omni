# WP-05-09 — Cash Position and Reports

## Objective

Provide read-only operational cash and banking reports from posted Phase 5 transactions.

## Read First

- AGENTS.md
- docs/phases/PHASE-05-CASH-AND-BANKING.md
- docs/work-packages/WP-05-08-bank-reconciliation.md

## Scope

Create reports for:

- current cash position
- account balances
- daily cash receipts
- daily cash disbursements
- bank activity
- e-wallet activity
- transfers in transit
- petty-cash activity
- unreconciled items
- reconciliation history
- cash movement by source type
- opening, movement, and closing balance by date range

## Functional Requirements

- Support as-of date and date-range filters.
- Exclude voided transactions.
- Reflect posted transactions only.
- Display reconciled and unreconciled amounts separately.
- Support account, account type, and transaction type filters.
- Provide print-friendly and CSV output.
- Use efficient queries and pagination.
- Avoid storing duplicate report balances.

## Permissions

- cash-reports.view
- cash-reports.export
- reconciliation-reports.view

## Tests

- Opening and closing balance
- Receipt and disbursement totals
- Transfer neutrality across all accounts
- As-of-date behavior
- Voided exclusions
- Reconciliation filters
- Authorization

## Acceptance Criteria

1. Operational balances reconcile to posted transactions.
2. Reports support as-of dates and ranges.
3. Transfers are represented correctly.
4. Reconciled and unreconciled activity is visible.
5. Tests pass.
6. No financial statements or ledger are created.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, reconciliation, and balance changes.
- Use document sequences where applicable.
- Never hard-delete posted cash or bank transactions.
- Preserve source document references and user attribution.
- Do not implement general-ledger entries, financial statements, tax return filing, payroll, or inventory costing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
