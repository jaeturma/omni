# WP-04-08 — Accounts Payable and Aging

## Objective

Provide supplier payable listings, statements, and aging based on posted supplier invoices and payment allocations.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-07-operating-expenses.md

## Scope

Create read-only reports for:

- open supplier invoices
- partially paid invoices
- overdue invoices
- payables by supplier
- supplier statements
- unapplied supplier advances
- aging detail and summary
- expenses payable, when applicable

## Aging Buckets

- Current
- 1–30 days overdue
- 31–60 days overdue
- 61–90 days overdue
- More than 90 days overdue

## Functional Requirements

- Support an as-of date.
- Calculate from due date.
- Exclude voided transactions.
- Reflect posted allocations only.
- Support filters, pagination, print view, and CSV export.
- Avoid storing redundant aging snapshots.

## Permissions

- payables.view
- payables.export
- supplier-statements.view

## Tests

- Bucket assignment
- Partial and full payment behavior
- As-of date
- Voided exclusions
- Supplier filtering
- Reconciliation and authorization

## Acceptance Criteria

1. Reports reconcile to supplier-invoice balances.
2. Aging and as-of dates are accurate.
3. Filters and exports work.
4. Tests pass.
5. No accounting subsidiary ledger is created.

## General Requirements

- Follow `AGENTS.md`.
- Use Laravel 13, Blade, Form Requests, policies, and focused Pest tests.
- Use decimal-safe server-side calculations.
- Use database transactions for posting, allocations, and status changes.
- Use document sequences where applicable.
- Never hard-delete posted financial records.
- Preserve gross purchases, discounts, withholding, payments, and balances separately.
- Do not implement general-ledger entries, inventory costing, financial statements, or BIR return filing.
- Do not modify unrelated modules.

## Completion Report

Report only:

1. Files changed
2. Commands and tests run
3. Acceptance criteria result
4. Remaining issues requiring owner action
