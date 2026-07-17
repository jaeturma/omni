# WP-03-03 — Sales Orders

## Objective

Create sales orders directly or by one-time conversion from approved quotations.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-02-quotations.md

## Scope

- Sales-order header and lines
- Direct creation
- Quotation conversion
- Customer purchase-order number
- Promised delivery date
- Payment terms
- Ordered, delivered, invoiced, cancelled, and remaining quantities
- Draft, confirmed, partially fulfilled, fulfilled, closed, and cancelled statuses

## Functional Requirements

- Prevent duplicate quotation conversion.
- Preserve source quotation.
- Prevent editing confirmed orders except through controlled actions.
- Support partial fulfillment.
- Calculate totals server-side.
- Do not reserve stock or create receivables.

## Permissions

- sales-orders.view
- sales-orders.create
- sales-orders.update
- sales-orders.confirm
- sales-orders.cancel

## Tests

- Direct order
- Quotation conversion
- Duplicate-conversion prevention
- Remaining-quantity calculations
- Authorization and cancellation
- No stock, receivable, or journal effects

## Acceptance Criteria

1. Orders may be direct or quotation-based.
2. Source traceability is preserved.
3. Remaining quantities are reliable.
4. Status controls work.
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
