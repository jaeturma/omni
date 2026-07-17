# WP-03-06 — Customer Payments and Allocations

## Objective

Record customer payments and allocate them safely across posted sales invoices.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-05-sales-invoices.md

## Scope

Create payment headers and allocation lines containing:

- payment number and date
- customer
- payment method and optional bank
- reference number
- gross settlement amount
- withholding amount
- other deductions
- net cash received
- unapplied amount
- invoice allocations
- draft, posted, partially applied, fully applied, and voided statuses

## Functional Requirements

- Support full, partial, multiple-invoice, and advance payments.
- Prevent payment and invoice over-allocation.
- Update invoice balances and statuses transactionally.
- Preserve unapplied customer balances.
- Separate cash received from withholding and deductions.
- Safely reverse allocations when voiding.
- Do not create journal entries.

## Permissions

- customer-payments.view
- customer-payments.create
- customer-payments.update
- customer-payments.post
- customer-payments.allocate
- customer-payments.void

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
2. Invoice balances remain accurate.
3. Unapplied balances are supported.
4. Voiding reverses effects.
5. Tests and fresh migrations pass.

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
