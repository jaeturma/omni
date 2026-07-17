# WP-03-05 — Sales Invoices

## Objective

Create posted sales invoices that establish operational customer receivable balances.

## Read First

- AGENTS.md
- docs/TAX_PROFILE.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-04-delivery-records.md

## Scope

Create invoice headers and lines supporting:

- direct service invoice
- order or delivery-based invoice
- invoice date and due date
- fiscal period
- customer, TIN, and address snapshots
- gross amount
- discount amount
- net sales amount
- expected withholding amount
- total receivable
- paid amount
- balance due
- draft, posted, partially paid, paid, overdue, and voided statuses

## Functional Requirements

- Calculate totals server-side.
- Prevent invoicing above invoiceable quantities.
- Issue controlled invoice number only on posting.
- Preserve historical snapshots.
- Establish an operational receivable on posting.
- Prevent editing after posting.
- Require controlled voiding with reason.
- Do not create journal entries or tax returns.

## Permissions

- sales-invoices.view
- sales-invoices.create
- sales-invoices.update
- sales-invoices.post
- sales-invoices.void
- sales-invoices.print

## Tests

- Draft and posting
- Number issuance once
- Direct service invoice
- Over-invoicing prevention
- Receivable balance
- Immutability and voiding
- Gross versus withholding separation
- Authorization

## Acceptance Criteria

1. Posted invoices create accurate operational balances.
2. Numbering is controlled.
3. Source quantities reconcile.
4. Amount components remain separate.
5. Tests and fresh migrations pass.
6. No ledger or BIR return is generated.

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
