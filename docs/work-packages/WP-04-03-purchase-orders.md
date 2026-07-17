# WP-04-03 — Purchase Orders

## Objective

Create purchase orders directly or by conversion from approved purchase requests and selected canvass records.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-02-purchase-requests-canvass.md

## Scope

Create purchase-order headers and lines with:

- purchase-order number and date
- supplier
- source request and canvass references
- delivery location
- expected delivery date
- supplier quotation reference
- payment terms
- freight and other charges
- product, service, or free-text item
- quantity and UOM snapshot
- unit cost
- discounts
- line and document totals
- ordered, received, billed, cancelled, and remaining quantities
- draft, approved, sent, partially received, received, closed, and cancelled statuses

## Functional Requirements

- Create directly or from an approved request.
- Prevent duplicate conversion where one-time conversion applies.
- Preserve source references.
- Calculate totals server-side.
- Prevent editing after approval except through controlled actions.
- Support partial receiving and billing.
- Do not reserve inventory or create payables.

## Permissions

- purchase-orders.view
- purchase-orders.create
- purchase-orders.update
- purchase-orders.approve
- purchase-orders.send
- purchase-orders.cancel
- purchase-orders.print

## Tests

- Direct purchase order
- Request conversion
- Duplicate-conversion prevention
- Totals and remaining quantities
- Approval and cancellation
- Authorization
- No stock, payable, or journal effects

## Acceptance Criteria

1. Orders may be direct or request-based.
2. Source traceability is preserved.
3. Remaining quantities are reliable.
4. Status controls work.
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
