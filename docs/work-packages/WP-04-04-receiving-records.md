# WP-04-04 — Receiving Records

## Objective

Record partial or complete receipt of goods and services against approved purchase orders without inventory valuation or accounting posting.

## Read First

- AGENTS.md
- docs/phases/PHASE-04-PURCHASING-AND-EXPENSES.md
- docs/work-packages/WP-04-03-purchase-orders.md

## Scope

Create receiving headers and lines containing:

- receiving number and date
- purchase order and supplier
- warehouse or delivery location
- delivery receipt number
- supplier invoice reference, optional
- inspection reference
- received_by
- inspected_by
- accepted_by
- received quantities
- accepted quantities
- rejected quantities
- rejection reasons
- notes
- draft, received, inspected, accepted, partially accepted, rejected, and cancelled statuses

## Functional Requirements

- Create from approved purchase orders.
- Allow partial receipt.
- Prevent over-receipt.
- Record accepted and rejected quantities separately.
- Update purchase-order receipt quantities transactionally.
- Print receiving records.
- Safely reverse quantities on cancellation.
- Do not create stock valuation, inventory balances, payables, or journal entries.

## Permissions

- receiving-records.view
- receiving-records.create
- receiving-records.inspect
- receiving-records.accept
- receiving-records.cancel
- receiving-records.print

## Tests

- Partial and full receipt
- Accepted and rejected quantities
- Over-receipt prevention
- Cancellation rollback
- Status and authorization
- No inventory valuation or accounting effects

## Acceptance Criteria

1. Receipt quantities reconcile to purchase orders.
2. Partial receipt works.
3. Over-receipt is blocked.
4. Rejected quantities are traceable.
5. Cancellation safely reverses quantities.
6. Tests and fresh migrations pass.

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
