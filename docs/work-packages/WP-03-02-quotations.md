# WP-03-02 — Quotations

## Objective

Create customer quotations for products and services without creating receivables or stock effects.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-01-sales-settings-workflow.md

## Scope

Create quotation headers and lines with:

- quotation number and date
- validity date
- customer and contact
- billing and delivery address snapshots
- reference and notes
- product or service
- description snapshot
- quantity and UOM snapshot
- unit price
- line and document discounts
- subtotal and grand total
- terms and conditions
- draft, submitted, approved, rejected, expired, converted, and cancelled statuses

## Functional Requirements

- Create and edit drafts.
- Calculate totals server-side.
- Approve, reject, expire, and cancel.
- Require cancellation reason.
- Print a clean quotation.
- Prevent editing after approval.
- Prepare safe conversion to a sales order.
- Do not create receivables, stock movements, or journals.

## Permissions

- quotations.view
- quotations.create
- quotations.update
- quotations.approve
- quotations.cancel
- quotations.print

## Tests

- CRUD and validation
- Product and service lines
- Decimal totals
- Authorization
- Approved-record immutability
- Cancellation reason
- No downstream financial effects

## Acceptance Criteria

1. Quotations support products and services.
2. Historical snapshots are preserved.
3. Numbering and status rules work.
4. Totals are decimal-safe.
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
