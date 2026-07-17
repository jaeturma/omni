# WP-04-05 — Supplier Invoices

## Objective

Create posted supplier invoices that establish operational accounts payable balances.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-04-receiving-records.md

## Scope

Create supplier-invoice headers and lines supporting:

- direct service or expense invoice
- purchase-order or receiving-based invoice
- internal document number
- supplier invoice number
- invoice date and due date
- fiscal period
- supplier, TIN, and address snapshots
- gross purchase amount
- discount amount
- freight and other charges
- withholding expected amount
- total payable
- paid amount
- balance due
- draft, posted, partially paid, paid, overdue, and voided statuses

## Functional Requirements

- Calculate totals server-side.
- Prevent billing above billable purchase-order or accepted quantities.
- Allow direct service invoices where appropriate.
- Issue controlled internal number only on posting.
- Prevent duplicate supplier invoice numbers per supplier.
- Preserve historical snapshots.
- Establish operational payable on posting.
- Prevent editing after posting.
- Require controlled voiding with reason.
- Do not create journal entries or tax returns.

## Permissions

- supplier-invoices.view
- supplier-invoices.create
- supplier-invoices.update
- supplier-invoices.post
- supplier-invoices.void
- supplier-invoices.print

## Tests

- Draft and posting
- Internal number issuance once
- Duplicate supplier invoice prevention
- Direct service invoice
- Over-billing prevention
- Payable balance
- Immutability and voiding
- Gross versus withholding separation
- Authorization

## Acceptance Criteria

1. Posted supplier invoices create accurate operational balances.
2. Duplicate supplier invoice numbers are prevented.
3. Source quantities reconcile.
4. Amount components remain separate.
5. Tests and fresh migrations pass.
6. No ledger or BIR return is generated.

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
