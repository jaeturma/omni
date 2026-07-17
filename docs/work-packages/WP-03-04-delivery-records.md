# WP-03-04 — Delivery Records

## Objective

Record partial or complete fulfillment of confirmed sales orders without inventory posting.

## Read First

- AGENTS.md
- docs/phases/PHASE-03-SALES-AND-COLLECTIONS.md
- docs/work-packages/WP-03-03-sales-orders.md

## Scope

Create delivery headers and lines containing:

- delivery number and date
- sales order and customer
- optional warehouse reference
- delivery address snapshot
- recipient details
- customer PO and inspection references
- delivered quantities
- received and acceptance details
- draft, released, delivered, accepted, and cancelled statuses

## Functional Requirements

- Create from confirmed orders.
- Allow partial deliveries.
- Prevent over-delivery.
- Update fulfilled and remaining quantities transactionally.
- Print delivery records.
- Safely reverse fulfillment quantities on cancellation.
- Do not create inventory movements, cost entries, or invoices automatically.

## Permissions

- deliveries.view
- deliveries.create
- deliveries.release
- deliveries.accept
- deliveries.cancel
- deliveries.print

## Tests

- Partial and full delivery
- Over-delivery prevention
- Cancellation rollback
- Status and authorization
- No inventory or accounting effects

## Acceptance Criteria

1. Delivery quantities reconcile to orders.
2. Partial delivery works.
3. Over-delivery is blocked.
4. Cancellation safely reverses quantities.
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
