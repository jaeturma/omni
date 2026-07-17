# WP-03-07 — Receivables and Aging

## Objective

Provide customer receivable listings, statements, and aging based on posted invoices and allocations.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-06-customer-payments-allocations.md

## Scope

Create read-only reports for:

- open invoices
- partially paid invoices
- overdue invoices
- receivables by customer
- government and private receivables
- customer statements
- unapplied payments
- aging detail and summary

## Aging Buckets

- Current
- 1–30 days overdue
- 31–60 days overdue
- 61–90 days overdue
- More than 90 days overdue

## Functional Requirements

- Support an as-of date.
- Calculate from invoice due date.
- Exclude voided transactions.
- Reflect posted allocations only.
- Support filters, pagination, print view, and CSV export.
- Avoid storing redundant aging snapshots.

## Permissions

- receivables.view
- receivables.export
- customer-statements.view

## Tests

- Bucket assignment
- Partial and full payment behavior
- As-of date
- Voided exclusions
- Government/private filters
- Reconciliation and authorization

## Acceptance Criteria

1. Reports reconcile to invoice balances.
2. Aging and as-of dates are accurate.
3. Filters and exports work.
4. Tests pass.
5. No accounting subsidiary ledger is created.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting and allocations.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
