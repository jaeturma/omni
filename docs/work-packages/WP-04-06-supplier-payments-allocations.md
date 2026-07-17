# WP-04-06 — Supplier Payments and Allocations

## Objective

Record supplier payments and allocate them safely across posted supplier invoices.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-05-supplier-invoices.md

## Scope

Create payment headers and allocation lines containing:

- payment number and date
- supplier
- payment method and optional bank
- cheque or transfer reference
- gross settlement amount
- withholding amount
- other deductions
- net cash paid
- unapplied amount
- supplier-invoice allocations
- draft, posted, partially applied, fully applied, and voided statuses

## Functional Requirements

- Support full, partial, multiple-invoice, and advance supplier payments.
- Prevent payment and invoice over-allocation.
- Update supplier-invoice balances and statuses transactionally.
- Preserve unapplied supplier advances.
- Separate cash paid from withholding and deductions.
- Safely reverse allocations when voiding.
- Do not create journal entries.

## Permissions

- supplier-payments.view
- supplier-payments.create
- supplier-payments.update
- supplier-payments.post
- supplier-payments.allocate
- supplier-payments.void

## Tests

- Full and partial payment
- One payment to multiple invoices
- Multiple payments to one invoice
- Unapplied balance
- Over-allocation prevention
- Withholding separation
- Voiding rollback
- Authorization

## Acceptance Criteria

1. Allocation logic is transaction-safe.
2. Supplier-invoice balances remain accurate.
3. Unapplied advances are supported.
4. Voiding reverses effects.
5. Tests and fresh migrations pass.

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
